<?php
/**
 * Script to build the documentation for pearweb
 *
 * This script runs the configure scripts, and generates the bightml, chunked, and pearweb
 * files for use in the pearweb project.
 * Used at http://pear.php.net/ and
 * http://ucommbieber.unl.edu/peardoc/build/, rsync://ucommbieber.unl.edu/peardoc
 *
 * Usage: php pearwebuild.php [--updatesvn] [--updatephd]
 */
$php_executable  = 'php';
$phd_executable  = 'phd';
$pear_executable = 'pear';
$tobuildlog      = ' >> ' . dirname(__FILE__) . '/../build/build.log 2>&1';
$fails           = 0;

if (isset($_SERVER['_'])) {
    $php_executable = $_SERVER['_'];
}

require_once 'Console/CommandLine.php';
$parser = new Console_CommandLine();
$parser->description = 'Builds the full manual';
$parser->version = '$Id: pearwebbuild.php 291863 2009-12-08 06:58:10Z cweiske $';
$parser->addOption('updatesvn', array(
    'long_name'   => '--updatesvn',
    'description' => 'Update peardoc from SVN',
    'action'      => 'StoreTrue'
));
$parser->addOption('updatephd', array(
    'long_name'   => '--updatephd',
    'description' => 'Update phd from SVN',
    'action'      => 'StoreTrue'
));
$parser->addOption('phpdir', array(
    'long_name'   => '--phpdir',
    'description' => 'Directory to copy php manual to',
    'action'      => 'StoreString'
));
$parser->addOption('downloaddir', array(
    'long_name'   => '--downloaddir',
    'description' => 'Directory to copy the downloadable manuals to',
    'action'      => 'StoreString'
));
$parser->addOption('hhc', array(
    'long_name'   => '--htmlhelpcompiler',
    'description' => 'Full path to HTML Help compiler executable',
    'action'      => 'StoreString'
));
$parser->addOption('norender', array(
    'long_name'   => '--norender',
    'description' => 'Do not render HTML, assume it already exists',
    'action'      => 'StoreTrue'
));

try {
    $cmdline = $parser->parse();
} catch (Exception $exc) {
    $parser->displayError($exc->getMessage());
}


if ($cmdline->options['updatesvn']) {
    chdir(dirname(dirname(dirname(__FILE__))));
    // Update svn
    shell_exec('svn checkout http://svn.php.net/repository/pear/peardoc/trunk peardoc');
}


if ($cmdline->options['updatephd']) {
    chdir(dirname(dirname(dirname(__FILE__))));
    // Update svn
    shell_exec('svn checkout http://svn.php.net/repository/phd/trunk phd');
    // Update phd
    shell_exec($pear_executable.' upgrade -f '.dirname(dirname(dirname(__FILE__))).'/phd/package.xml');
}


function logAndEcho($message)
{
    echo $message . PHP_EOL;
    file_put_contents(
        dirname(__FILE__) . '/../build/build.log',
        '[pearwebbuild] ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

/**
 * Calls passthru() and checks the return value
 *
 * @param string $command Command to passthru()
 *
 * @return void
 * @uses   checkReturn()
 */
function passthruCheckReturn($command)
{
    passthru($command, $return);
    checkReturn($return, $command);
}

/**
 * Check the given return value and show error when it
 * is != 0. Also die(), since the error should not occur.
 *
 * @param integer $retval  Return code
 * @param string  $command Command that was executed
 *
 * @return void
 */
function checkReturn($retval, $command = null)
{
    if ($retval === 0) {
        return;
    }
    logAndEcho(' Return value is not 0 but ' . $retval);
    if ($command) {
        logAndEcho( 'Command was: ' . $command);
    }
    exit(9);
}

$render = !$cmdline->options['norender'];

chdir(dirname(dirname(__FILE__)));

//only clean old cruft if we are rendering
$render && passthru('rm -rf build/ && mkdir build');

logAndEcho("Starting Build\n".date('Y-m-d H:i:s'));

$langs = glob('??{,_??}', GLOB_ONLYDIR | GLOB_BRACE);
foreach ($langs as $lang) {
    $output = '';
    logAndEcho("Building peardoc/$lang with php configure.php --lang=$lang");
    if ($render) {
        exec("$php_executable configure.php --no-display-untranslated --lang=$lang", $output, $return);
    } else {
        logAndEcho(' Skipping configure call');
        $return = 0;
    }

    if ($return != 0) {
        logAndEcho("Error configuring --lang=$lang:");
        logAndEcho(implode("\n", $output) . "\n");
        ++$fails;
        continue;
    }

    logAndEcho("Now running phd for $lang");

    $extracopyfiles = '';

    //all output formats
    if ($render) {
        logAndEcho(' Rendering all pear themes');
        exec(
            "$phd_executable -L $lang -P PEAR -o build/$lang -d .manual.xml" . $tobuildlog,
            $output,
            $return
        );
        checkReturn($return);
    } else {
        logAndEcho(' Skipping rendering');
    }

    // bzip2
    logAndEcho(' Packing up pearbightml downloadable files');
    passthruCheckReturn(
        'bzip2'
        . ' ' . escapeshellarg("build/$lang/pear-bigxhtml.html")
        . ' --stdout'
        . ' > ' . escapeshellarg("build/$lang/pear_manual_$lang.html.bz2")
    );

    // gz
    passthruCheckReturn(
        'gzip'
        . ' ' . escapeshellarg("build/$lang/pear-bigxhtml.html")
        . ' --stdout'
        . ' > ' . escapeshellarg("build/$lang/pear_manual_$lang.html.gz")
    );

    // zip
    passthruCheckReturn(
        "cd build/$lang/ &&"
        . ' mv pear-bigxhtml.html ' . escapeshellarg("pear_manual_$lang.html") . ' &&'
        . ' zip -q'
        . ' ' . escapeshellarg("pear_manual_$lang.html.zip")
        . ' ' . escapeshellarg("pear_manual_$lang.html")
        . $tobuildlog
    );

    logAndEcho(' Packing up pearchunked downloadable files');

    // tar.bz2
    passthruCheckReturn(
        "cd build/$lang/ &&"
        . " mv pear-chunked-xhtml pear_manual_$lang &&"
        . ' tar cj --to-stdout'
        . ' ' . escapeshellarg("pear_manual_$lang")
        . ' > '
        . escapeshellarg("pear_manual_$lang.tar.bz2")
    );
    // tar.gz
    passthruCheckReturn(
        "cd build/$lang/ &&"
        . ' tar cfz ' . escapeshellarg("pear_manual_$lang.tar.gz")
        . ' ' . escapeshellarg("pear_manual_$lang")
        . $tobuildlog
    );
    // zip
    passthruCheckReturn(
        "cd build/$lang/ &&"
        . 'zip -r'
        . ' ' . escapeshellarg("pear_manual_$lang.zip")
        . ' '
        . escapeshellarg("pear_manual_$lang")
        . $tobuildlog
    );

    //restore old directory name for re-runs without recompilation
    passthruCheckReturn(
        "cd build/$lang/ &&"
        . " mv pear_manual_$lang pear-chunked-xhtml &&"
        . " mv pear_manual_$lang.html pear-bigxhtml.html"
    );


    //pearweb package tocs
    if ($lang == 'en') {
        if ($render) {
            logAndEcho(' Building pearweb package tocs');
            exec(
                "$php_executable scripts/pearwebpackagetocs.php" . $tobuildlog,
                $output, $return
            );
            checkReturn($return);
        } else {
            logAndEcho(' Skipping rendering pearweb package tocs');
        }
    }


    if ($cmdline->options['phpdir']) {
        //copy pearweb build to directory
        $phplangdir = $cmdline->options['phpdir'] . '/' . $lang;
        if (file_exists($phplangdir)) {
            shell_exec('rm -rf ' . escapeshellarg($phplangdir) . $tobuildlog);
        }
        if (!file_exists($cmdline->options['phpdir'])) {
            mkdir($cmdline->options['phpdir'], 0755, true);
        }
        exec(
            "mv build/$lang/pear-web " . escapeshellarg($phplangdir) . $tobuildlog,
            $output, $return
        );
        checkReturn($return);

        //move packagetoc over, too
        if (file_exists("build/$lang/packagetocs")) {
            exec(
                "mv build/$lang/packagetocs " . escapeshellarg($phplangdir . '/packagetocs/') . $tobuildlog,
                $output, $return
            );
            checkReturn($return);
        }
    }

    if ($cmdline->options['hhc']) {
        // we need to chmod the chm dir since the html help compiler
        // is being run as an own user
        if ($render) {
            logAndEcho(' Compiling chm manual');
            passthruCheckReturn(
                "chmod og+w build/$lang/pear-chm &&"
                . " cd build/$lang/pear-chm && "
                . $cmdline->options['hhc']
                . ' pear_manual_' . $lang . '.hhp'
                . $tobuildlog
            );
        } else {
            logAndEcho(' Skipping compiling chm manual');
        }

        $chmfile = "build/$lang/chm/pear_manual_$lang.chm";
        if (file_exists($chmfile)) {
            $extracopyfiles .= ' ' . escapeshellarg($chmfile);
        } else {
            logAndEcho('Tried to compile chm, but chm file does not exist.');
            logAndEcho($chmfile);
        }
    }

    // move compressed downloadable files to downloaddir
    if ($cmdline->options['downloaddir'] !== null) {
        if (is_writeable($cmdline->options['downloaddir'])) {
            logAndEcho(
                ' Moving downloadable file to '
                . escapeshellarg($cmdline->options['downloaddir'])
            );
            exec(
                'mv'
                . ' ' . escapeshellarg("build/$lang/pear_manual_$lang.html.bz2")
                . ' ' . escapeshellarg("build/$lang/pear_manual_$lang.html.gz")
                . ' ' . escapeshellarg("build/$lang/pear_manual_$lang.html.zip")
                . ' ' . escapeshellarg("build/$lang/pear_manual_$lang.tar.bz2")
                . ' ' . escapeshellarg("build/$lang/pear_manual_$lang.tar.gz")
                . ' ' . escapeshellarg("build/$lang/pear_manual_$lang.zip")
                . ' ' . $extracopyfiles
                . ' ' . escapeshellarg($cmdline->options['downloaddir'])
                . $tobuildlog,
                $output, $return
            );
            checkReturn($return);
        }
    }

    // Remove chm build until we can get a chm compiler going.
    //shell_exec("$phd_executable -f xhtml -t pearchm -o build/$lang -d .manual.xml" . $tobuildlog);

    logAndEcho(" Finished peardoc/$lang");
    logAndEcho("\n");
}

logAndEcho("Build complete\n" . date('Y-m-d H:i:s'));
$fails && logAndEcho($fails . ' failed builds');

exit($fails);
?>
