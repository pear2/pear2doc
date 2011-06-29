#!/usr/bin/env php
<?php
$pwpt = new PearwebPackageTocs();
$pwpt->run();

class PearwebPackageTocs
{
    /**
     * Array of parsed command line options
     *
     * @var array
     */
    protected $opts = array();


    public function run()
    {
        $this->opts = $this->parseArgs();
        try {
            $indexFile = $this->getIndexFile();
            $outputDir = $this->ensureOutputDirExists($this->opts['out_dir']);
            $nCount = $this->createPackageTocs($indexFile, $outputDir);
            if (!$this->opts['quiet']) {
                echo 'Generated TOCs for ' . $nCount . ' packages' . "\n";
            }
        } catch (Exception $e) {
            echo "Exception:\n";
            echo $e->getMessage() . "\n";
            exit($e->getCode());
        }
    }



    /**
     * Parses commandline arguments, displays help if necessary and returns
     * an array of parsed parameters.
     *
     * @return array Key-value pair array, parameter name is key, value is value.
     */
    protected function parseArgs()
    {
        require_once 'Console/CommandLine.php';
        $parser = new Console_CommandLine(
            array(
                'description' => 'Creates package Table of Contents (TOC) files for pearweb',
                'version'     => '$Id: pearwebpackagetocs.php 308803 2011-03-01 08:09:06Z cweiske $'
            )
        );

        $parser->addOption('toc_depth', array(
            'short_name'  => '-d',
            'long_name'   => '--toc-depth',
            'action'      => 'StoreInt',
            'description' => 'How many levels the TOCs shall have',
            'default'     => 1,
        ));
        $parser->addOption('index_file', array(
            'short_name'  => '-i',
            'long_name'   => '--index',
            'action'      => 'StoreString',
            'description' => 'File name of PhD sqlite index file',
            'default'     => dirname(__FILE__) . '/../build/en/index.sqlite',
        ));
        $parser->addOption('out_dir', array(
            'short_name'  => '-o',
            'long_name'   => '--output-directory',
            'action'      => 'StoreString',
            'description' => 'In which directory the written TOC files shall be written',
            'default'     => dirname(__FILE__) . '/../build/en/packagetocs/',
        ));

        $parser->addOption('quiet', array(
            'short_name'  => '-q',
            'long_name'   => '--quiet',
            'action'      => 'StoreTrue',
            'description' => 'Do not show any non-error output',
            'default'     => false,
        ));
        $parser->addOption('progress', array(
            'short_name'  => '-p',
            'long_name'   => '--progress',
            'action'      => 'StoreTrue',
            'description' => 'Show progress indicator (dots)',
            'default'     => false,
        ));
        $parser->addOption('verbose', array(
            'short_name'  => '-v',
            'long_name'   => '--verbose',
            'action'      => 'StoreTrue',
            'description' => 'Display detailled messages',
            'default'     => false,
        ));

        try {
            $result = $parser->parse();

            if ($result->options['verbose'] && $result->options['progress']) {
                $result->options['progress'] = false;
            }
            if ($result->options['verbose'] && $result->options['quiet']) {
                $result->options['quiet'] = false;
            }

            return $result->options;
        } catch (Exception $exc) {
            $parser->displayError($exc->getMessage());
            exit(-2);
        }
    }


    /**
     * Checks if the given output directory exists and is writable.
     * Tries to create it if it does not exist.
     * Adds a trailing slash if it hasn't one
     *
     * @param string $outputDir Output directory
     *
     * @return string Output directory with trailing slash
     *
     * @throws Exception When the directory could not be created or is not writable.
     */
    protected function ensureOutputDirExists($outputDir)
    {
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new Exception(
                    'Output directory could not be created'
                    . "\n" . $outputDir, -1
                );
            }
        }
        if (!is_writable($outputDir)) {
            throw new Exception('Output directory is not writable', -2);
        }

        if (substr($outputDir, -1) !== '/') {
            $outputDir .= '/';
        }

        $this->opts['verbose'] && printf("Output dir: %s\n", $outputDir);

        return $outputDir;
    }



    /**
     * Reads the index file from the options and checks readability.
     *
     * @return string Path of serialized index file
     *
     * @throws Exception When no index file is given or none could be found.
     */
    protected function getIndexFile()
    {
        $indexFile = $this->opts['index_file'];

        if (!file_exists($indexFile)) {
            throw new Exception(
                'Index file does not exist: ' . $indexFile . "\n"
                . 'Run phd first.', -11
            );
        }
        if (!is_readable($indexFile)) {
            throw new Exception(
                'Index file is not readable: ' . $indexFile, -12
            );
        }

        if ($this->opts['verbose']) {
            printf("Index file: %s\n", $indexFile);
        }

        return $indexFile;
    }



    protected function createPearwebPackageToc(array $arPackage, SQLite3 $slite, $depth = 0)
    {
        $stmt = $slite->prepare('SELECT * FROM ids WHERE parent_id = :id');
        $stmt->bindValue(':id', $arPackage['docbook_id'], SQLITE3_TEXT);
        $sqlres  = $stmt->execute();
        $arChunk = $sqlres->fetchArray(SQLITE3_ASSOC);
        if (!is_array($arChunk)) {
            $depth == 0 && $this->opts['verbose']
                && printf("No children for %s\n", $arPackage['docbook_id']);
            return '';
        }

        $sp = str_repeat(' ', $depth * 2);
        $content = '';
        $depth > 0 && $content .= "\n";
        $content .= $sp . "<ul class=\"packagetoc\">\n";
        do {
            $long  = $arChunk['ldesc'];
            $short = $arChunk['sdesc'];
            //Laurent's package tocs did not look so good with the long options:
            // http://euk1.php.net/package/PHP_CompatInfo/docs
            // http://news.php.net/php.pear.webmaster/6355
            //$desc = ($long ? $long : $short) . '</a>';
            $desc = htmlentities($short ? $short : $long) . '</a>';
            $url = '/manual/en/' . $arChunk['filename'] . '.php';
            if ($arChunk['chunk'] == 0) {
                $url .= '#' . $arChunk['docbook_id'];
            }
            $content .= $sp . " <li><a href=\"$url\">" . $desc;
            if ($depth < $this->opts['toc_depth'] - 1) {
                $content .= $this->createPearwebPackageToc($arChunk, $slite, $depth + 1);
            }

            $content .= "</li>\n";;
        } while ($arChunk = $sqlres->fetchArray(SQLITE3_ASSOC));

        $content .= $sp . "</ul>\n";
        $depth > 0 && $content .= str_repeat(' ', $depth * 2 - 1);

        return $content;
    }



    /**
     * Creates TOC files for all packages.
     *
     * @param string $indexFile Path of index file
     * @param string $outputDir Output directory with trailing slash
     *
     * @return integer Number of created package TOC files
     */
    protected function createPackageTocs($indexFile, $outputDir)
    {
        $slite = new SQLite3($indexFile, SQLITE3_OPEN_READONLY);

        $sqlres = $slite->query(
            'SELECT packages.* FROM ids AS packages, ids AS categories'
            . ' WHERE categories.parent_id = "packages"'
            . ' AND packages.parent_id = categories.docbook_id'
            . ' ORDER BY packages.docbook_id'
        );
        $nCount = 0;

        while ($arPackage = $sqlres->fetchArray(SQLITE3_ASSOC)) {
            $strToc = $this->createPearwebPackageToc($arPackage, $slite);
            if ($strToc) {
                $this->opts['verbose'] && printf("Toc for %s\n", $arPackage['ldesc']);
                $strName = strtolower($arPackage['ldesc']);
                file_put_contents(
                    $outputDir . $strName . '.htm',
                    $strToc
                );
                ++$nCount;
                $this->opts['progress'] && print('.');
            } else {
                $this->opts['verbose'] &&  printf("NO toc for %s\n", $arPackage['ldesc']);
                $this->opts['progress'] && print('n');
            }
        }

        $this->opts['progress'] && print("\n");
        return $nCount;
    }
}
?>
