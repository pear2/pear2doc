#!/usr/bin/env php
<?php
/**
* Configure the manual:
* - select language
* - create chapters.ent listing all files
* - create the giant .manual.xml file (much faster than using manual.xml and
*    pulling in single files)
* - validate the manual against Docbook 5 DTD
*
*
*/

$opts = parseArgs();
$GLOBALS['display_verbose'] = $opts['verbose'];
$GLOBALS['errorcode']       = 0;
$GLOBALS['quiet']           = $opts['quiet'];
if (empty($opts['manual_file'])) {
    $opts['manual_file'] = 'manual.xml';
}
if (empty($opts['giant_file'])) {
    $opts['giant_file'] = '.' . $opts['manual_file'];
}

try {
    createVersionEnt('entities/version.ent');
    createChaptersEnt(
        $opts['language'], $opts['display_untranslated'],
        $opts['use_broken_translation_filename']
            ? $opts['broken_translation_filename'] : null
    );
    createGiant(
        $opts['manual_file'], $opts['giant_file'],
        $opts['validate'], $opts['hide_xml_errors']
    );
    logMsg('Now call PhD:');
    logMsg(' phd'
        . ' -L ' . $opts['language']
        . ' -P PEAR -f xhtml'
        . ' -o build/' . $opts['language']
        . ' -d ' . $opts['giant_file']
    );
} catch (Exception $e) {
    logErr('Exception:');
    logErr($e->getMessage());
}


exit($GLOBALS['errorcode']);


/**
* Parses commandline arguments, displays help if necessary and returns
* an array of parsed parameters.
*
* @return array Key-value pair array, parameter name is key, value is value.
*/
function parseArgs()
{
    require_once 'Console/CommandLine.php';
    $parser = new Console_CommandLine(array(
        'description' => 'configure the pear manual build',
        'version'     => '$Id: configure.php 309663 2011-03-24 19:30:59Z rquadling $'
    ));

    $parser->addOption('verbose', array(
        'short_name'  => '-v',
        'long_name'   => '--verbose',
        'action'      => 'StoreTrue',
        'description' => 'Display detailled messages',
        'default'     => false
    ));
    $parser->addOption('quiet', array(
        'short_name'  => '-q',
        'long_name'   => '--quiet',
        'action'      => 'StoreTrue',
        'description' => 'Hide all non-error messages',
        'default'     => false
    ));
    $parser->addOption('validate', array(
        'short_name'  => '-n',
        'long_name'   => '--no-validate',
        'action'      => 'StoreFalse',
        'description' => 'Validate the giant .manual.xml file',
        'default'     => true
    ));
    $parser->addOption('giant_file', array(
        'short_name'  => '-h',
        'long_name'   => '--output',
        'action'      => 'StoreString',
        'description' => 'File name of merged (giant) manual',
        'default'     => null
    ));
    $parser->addOption('display_untranslated', array(
        'long_name'   => '--no-display-untranslated',
        'action'      => 'StoreFalse',
        'description' => 'Do not display untranslated files',
        'default'     => true
    ));
    $parser->addOption('broken_translation_filename', array(
        'long_name'   => '--broken-translation-filename',
        'help_name'   => 'file',
        'action'      => 'StoreString',
        'description' => 'Filename of list with broken/outdated translation files',
        'default'     => 'broken-files.txt'
    ));
    $parser->addOption('use_broken_translation_filename', array(
        'long_name'   => '--ignore-broken-file-listing',
        'action'      => 'StoreFalse',
        'description' => 'Do not use broken-translation-filename setting',
        'default'     => true
    ));
    $parser->addOption('hide_xml_errors', array(
        'long_name'   => '--no-hide-xml-errors',
        'action'      => 'StoreFalse',
        'description' => 'Do not hide xml errors',
        'default'     => true
    ));
    $parser->addArgument('manual_file', array(
        'description' => 'xml manual file',
        'optional'    => true,
        'help_name'   => 'docbook file'
    ));

    //language directories like "en" and "pt_BR"
    $langs = glob('??{,_??}', GLOB_ONLYDIR | GLOB_BRACE);
    $parser->addOption('language', array(
        'short_name'  => '-l',
        'long_name'   => '--language',
        'action'      => 'StoreString',
        'help_name'   => 'LANG',
        'description' => 'Manual language',
        'choices'     => $langs,
        'default'     => 'en'
    ));

    try {
        $result = $parser->parse();
        return array_merge($result->options, $result->args);
    } catch (Exception $exc) {
        $parser->displayError($exc->getMessage());
        exit(-2);
    }
}

function createVersionEnt($entitiesfile)
{
    file_put_contents(
        $entitiesfile,
        str_replace(
            array('@PEARDOC_BUILD_DATE@', '@PEARDOC_COPYRIGHT_YEAR@'),
            array(date('Y-m-d'), date('Y')),
            file_get_contents($entitiesfile . '.in')
        )
    );
}

/**
* Create the chapters.ent file which contains entity references
* to every manual file.
* English files are used as fallback in case a translation misses a file.
*
* @param string  $lang                  Language to use ("en", "de", "pt_BR")
* @param boolean $bDisplayUntranslated  If the names of untranslated files shall
*                                       be displayed
* @param string  $brokenTranslationFile Name of file listing broken translation
*                                       files. Those files will not be used.
*                                       Set it to null to deactivate it.
*
* @return void
*/
function createChaptersEnt($lang, $bDisplayUntranslated = true,
    $brokenTranslationFile = null
) {
    logMsg('Generating chapters.ent for ' . $lang);

    $brokenFilePath = $lang . '/' . $brokenTranslationFile;
    if ($brokenTranslationFile && file_exists($brokenFilePath)) {
        logMsg(' Ignoring broken translations listed in ' . $brokenFilePath);
        $arBroken = array_flip(
            array_map('trim', file($brokenFilePath))
        );
    } else {
        $arBroken = array();
    }

    $cwd = getcwd();
    chdir('en');
    $arXmlFiles = glob('{,*/,*/*/,*/*/*/,*/*/*/*/,*/*/*/*/*/}*.xml', GLOB_BRACE);
    $arPhpFiles = glob('{,*/,*/*/,*/*/*/,*/*/*/*/,*/*/*/*/*/}*.{php,txt}', GLOB_BRACE);
    chdir($cwd);

    $hdl = fopen('chapters.ent', 'w');
    fputs($hdl, "<!-- DON'T TOUCH - AUTOGENERATED BY configure.php -->\n");

    $nUntranslated = $nUntranslatedPhp = 0;

    //XML files
    foreach ($arXmlFiles as $file) {
        $langfile = $lang . '/' . $file;
        if ($lang == 'en'
            || (file_exists($langfile) && !isset($arBroken[$file]))
        ) {
            $entfile = $langfile;
        } else {
            ++$nUntranslated;
            if ($bDisplayUntranslated) {
                logMsg(' Untranslated file: ' . $langfile);
            }
            $entfile = 'en/' . $file;
        }
        $name = str_replace(array('/', '_'), array('.', '-'), substr($file, 0, -4));
        fputs($hdl, '<!ENTITY ' . $name . ' SYSTEM "' . $entfile . "\">\n");
    }

    //PHP example files
    foreach ($arPhpFiles as $file) {
        $langfile = $lang . '/' . $file;
        if ($lang == 'en'
            || (file_exists($langfile) && !isset($arBroken[$file]))
        ) {
            $entfile = $langfile;
        } else {
            ++$nUntranslatedPhp;
            if ($bDisplayUntranslated) {
                logMsg(' Untranslated example: ' . $langfile);
            }
            $entfile = 'en/' . $file;
        }
        $name = str_replace(array('/', '_'), array('.', '-'), $file);
        fputs($hdl, '<!ENTITY ' . $name . ' "' . $entfile . "\">\n");
    }

    fclose($hdl);

    logMsg(' ' . count($arXmlFiles) . ' xml files');
    logMsg(' ' . count($arPhpFiles) . ' php example files');

    if ($nUntranslated > 1) {
        logMsg(
            ' ' . $nUntranslated . ' untranslated files'
            . ' (' . number_format(100 / count($arXmlFiles) * $nUntranslated, 2) . '%)'
        );
    }
    if ($nUntranslatedPhp > 1) {
        logMsg(
            ' ' . $nUntranslatedPhp . ' untranslated example files'
            . ' (' . number_format(100 / count($arPhpFiles) * $nUntranslatedPhp, 2) . '%)'
        );
    }
    logMsg(' done');
}

/**
* Create one giant xml file by incluing every entity into manual.xml
* and saving it as .manual.xml
*
* @param string  $strManual   Name of source docbook xml file
* @param string  $strGiant    Name of merged (giant) xml file
* @param boolean $bValidate   If the generated manual shall be validated
* @param boolean $bHideErrors If errors loading and validating the manual shall
*                             be hidden using output buffering. This will
*                             hide the point of failure when php or libxml2
*                             crashes
*
* @return void
*/
function createGiant(
    $strManual = 'manual.xml', $strGiant = '.manual.xml',
    $bValidate = false, $bHideErrors = true
) {
    logMsg('Loading manual into one giant file');
    $dom = new DOMDocument();
    $dom->formatOutput = false;

    if ($bHideErrors) {
        ob_start();
    }
    $bLoaded = $dom->load($strManual, LIBXML_NOENT);
    if ($bHideErrors) {
        $msg = ob_get_clean();
        if ($msg) {
            $GLOBALS['errorcode'] = 3;
            logErr(' There were warnings loading the manual');
        }
    }
    if (!$bLoaded) {
        if (isset($msg) && $msg) {
            logErr($msg);
        }
        $GLOBALS['errorcode'] = 4;
        throw new Exception('Failed to load manual.xml');
    }
    $dom->xinclude();

    logMsg(' Validating');
    if ($bHideErrors) {
        ob_start();
    }
    $bValid = $dom->validate();
    if ($bHideErrors) {
        $msg = ob_get_clean();
    }
    if (!$bValid) {
        logErr('  Manual has errors. Use');
        logErr('   xmllint --valid --noout ' . $strManual);
        logErr('   xmllint --valid --noout ' . $strGiant);
        logErr('  to get detailled error messages.');
        $GLOBALS['errorcode'] = 5;
    }

    $dom->save($strGiant);
    logMsg(' done');
}

/**
* Just log a message to stdout.
*
* @param string  $msg      Message to display
* @param boolean $bVerbose Mark message as "verbose", so that it's only shown
*                          --verbose has been passed as option to the script
* @param boolean $bError   Mark message as "error" so that it gets displayed
*                          even when --quiet is used.
*
* @return void
*/
function logMsg($msg, $bVerbose = false, $bError = false)
{
    if ($GLOBALS['quiet'] && !$bError) {
        return;
    }
    if (!$bVerbose || $GLOBALS['display_verbose']) {
        echo $msg . "\n";
    }
}

/**
* Log an error.
* Shortcut for logMsg()
*
* @param string $msg Message to display
*
* @return void
*/
function logErr($msg)
{
    logMsg($msg, false, true);
}

?>