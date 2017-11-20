<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sugarcrm\ProfessorM\PackageGenerator;
use org\bovigo\vfs\vfsStream;

class PackageGeneratorTest extends TestCase
{

    public function testShouldIncludeFileInZipValidFileMac(){
        $pg = new PackageGenerator();
        $this->assertTrue($pg->shouldIncludeFileInZip("src/custom/Extension/modules/Accounts/Ext/WirelessLayoutdefs/pr_professors_accounts_Accounts.php"));
    }

    public function testShouldIncludeFileInZipValidFileWindows(){
        $pg = new PackageGenerator();
        $this->assertTrue($pg->shouldIncludeFileInZip("src\\custom\\Extension\\modules\\Accounts\\Ext\\WirelessLayoutdefs\\pr_professors_accounts_Accounts.php"));
    }

    public function testShouldIncludeFileInZipFileInCustomApplicationExtMac(){
        $pg = new PackageGenerator();
        $this->assertFalse($pg->shouldIncludeFileInZip("src/custom/application/Ext/test.php"));
    }

    public function testShouldIncludeFileInZipFileInCustomApplicationExtWindows(){
        $pg = new PackageGenerator();
        $this->assertFalse($pg->shouldIncludeFileInZip("src\\custom\\application\\Ext\\test.php"));
    }

    public function testShouldIncludeFileInZipFileInCustomModulesModuleNameExtMac(){
        $pg = new PackageGenerator();
        $this->assertFalse($pg->shouldIncludeFileInZip("src/custom/modules/test/Ext/excludeme.php"));
    }

    public function testShouldIncludeFileInZipFileInCustomModulesModuleNameExtWindows(){
        $pg = new PackageGenerator();
        $this->assertFalse($pg->shouldIncludeFileInZip("src\\custom\\modules\\test\\Ext\\excludeme.php"));
    }

    public function testGetVersionNoDecimals(){
        $pg = new PackageGenerator();
        $this -> assertEquals(1, $pg -> getVersion(1));
    }

    public function testGetVersionDecimals(){
        $pg = new PackageGenerator();
        $this -> assertEquals("1.2.3", $pg -> getVersion("1.2.3"));
    }

    public function testGetVersionFromFile(){
        $root = vfsStream::setup();
        vfsStream::newFile("version") -> at($root) -> withContent("1.2.3");

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());
        $this -> assertEquals("1.2.3", $pg -> getVersion(""));
    }

    public function testGetVersionFromParamWhenVersionFileIsAvail(){
        $root = vfsStream::setup();
        vfsStream::newFile("version") -> at($root) -> withContent("1.2.3");

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());
        $this -> assertEquals("1.5", $pg -> getVersion("1.5"));
    }

    public function testGetZipFilePathValidParamsReleasesDirectoryDoesNotExist(){
        $root = vfsStream::setup();

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $this -> assertFalse($root -> hasChild("releases"));

        $this -> assertEquals("releases" . DIRECTORY_SEPARATOR . "sugarcrm-ProfessorM-1.5.zip",
            $pg -> getZipFilePath("1.5", "ProfessorM", "./pack.php"));

        $this -> assertTrue($root -> hasChild("releases"));

    }

    public function testGetZipFilePathValidParamsReleasesDirectoryAlreadyExists(){
        $root = vfsStream::setup();
        vfsStream::newDirectory("releases") -> at($root);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $this -> assertTrue($root -> hasChild("releases"));

        $this -> assertEquals("releases" . DIRECTORY_SEPARATOR . "sugarcrm-ProfessorM-1.5.zip",
            $pg -> getZipFilePath("1.5", "ProfessorM", "./pack.php"));

        $this -> assertTrue($root -> hasChild("releases"));

    }

    public function testGetZipFilePathEmptyVersion(){
        $pg = new PackageGenerator();
        $this -> expectException(Exception::class);

        $pg -> getZipFilePath("", "ProfessorM", "./pack.php");

    }

    public function testGetFileArraysForZipSingleFileToInclude(){
        $root = vfsStream::setup();
        $srcDirectory = vfsStream::newDirectory("src") -> at($root);
        vfsStream::newFile("myfile.php") -> at($srcDirectory);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $fileArrays = $pg -> getFileArraysForZip("src");
        $filesToInclude = $fileArrays["filesToInclude"];
        $filesToExclude = $fileArrays["filesToExclude"];
        $this -> assertEquals(1, count($filesToInclude));
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "myfile.php", $filesToInclude[0]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "myfile.php", $filesToInclude[0]["fileReal"]);
        $this -> assertEquals(0, count($filesToExclude));
    }

    public function testGetFileArraysForZipSingleFileToExclude(){
        $root = vfsStream::setup();
        $srcDirectory = vfsStream::newDirectory("src") -> at($root);
        $customDirectory = vfsStream::newDirectory("custom") -> at($srcDirectory);
        $applicationDirectory = vfsStream::newDirectory("application") -> at($customDirectory);
        $ExtDirectory = vfsStream::newDirectory("Ext") -> at($applicationDirectory);
        vfsStream::newFile("myfile.php") -> at($ExtDirectory);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $fileArrays = $pg -> getFileArraysForZip("src");
        $filesToInclude = $fileArrays["filesToInclude"];
        $filesToExclude = $fileArrays["filesToExclude"];
        $this -> assertEquals(0, count($filesToInclude));
        $this -> assertEquals(1, count($filesToExclude));
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "custom" . DIRECTORY_SEPARATOR . "application"
            . DIRECTORY_SEPARATOR . "Ext" . DIRECTORY_SEPARATOR . "myfile.php", $filesToExclude[0]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "custom" . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR .
            "Ext" . DIRECTORY_SEPARATOR . "myfile.php", $filesToExclude[0]["fileReal"]);
    }

    public function testGetFileArrayForZipMultipleFiles(){
        /*
         * Files to be included:
         *  [*] src/language/application/en_us.lang.php
         *  [*] src/icons/default/images/PR_Professors.gif
         *  [*] src/icons/default/images/CreatePR_Professors.gif
         *
         * Files to be excluded:
         * [*] src/custom/application/Ext/test.php
         * [*] src/custom/modules/test/Ext/excludeme.php
         */

        $root = vfsStream::setup();
        $srcDirectory = vfsStream::newDirectory("src") -> at($root);
        $languageDirectory = vfsStream::newDirectory("language") -> at($srcDirectory);
        $applicationUnderLanguageDirectory = vfsStream::newDirectory("application") -> at($languageDirectory);
        $iconsDirectory = vfsStream::newDirectory("icons") -> at($srcDirectory);
        $defaultDirectory = vfsStream::newDirectory("default") -> at($iconsDirectory);
        $imagesDirectory = vfsStream::newDirectory("images") -> at($defaultDirectory);
        $customDirectory = vfsStream::newDirectory("custom") -> at($srcDirectory);
        $applicationDirectory = vfsStream::newDirectory("application") -> at($customDirectory);
        $ExtDirectory = vfsStream::newDirectory("Ext") -> at($applicationDirectory);
        $modulesDirectory = vfsStream::newDirectory("modules") -> at($customDirectory);
        $testDirectory = vfsStream::newDirectory("test") -> at($modulesDirectory);
        $ExtUnderTestDirectory = vfsStream::newDirectory("Ext") -> at($testDirectory);

        vfsStream::newFile("en_us.lang.php") -> at($applicationUnderLanguageDirectory);
        vfsStream::newFile("PR_Professors.gif") -> at($imagesDirectory);
        vfsStream::newFile("CreatePR_Professors.gif") -> at($imagesDirectory);
        vfsStream::newFile("test.php") -> at($ExtDirectory);
        vfsStream::newFile("excludeme.php") -> at($ExtUnderTestDirectory);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $fileArrays = $pg -> getFileArraysForZip("src");
        $filesToInclude = $fileArrays["filesToInclude"];
        $filesToExclude = $fileArrays["filesToExclude"];

        $this -> assertEquals(3, count($filesToInclude));
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "language" . DIRECTORY_SEPARATOR . "application"
            . DIRECTORY_SEPARATOR . "en_us.lang.php", $filesToInclude[0]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "language" . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR .
            "en_us.lang.php", $filesToInclude[0]["fileReal"]);
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default" .
            DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "PR_Professors.gif", $filesToInclude[1]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default" . DIRECTORY_SEPARATOR . "images"
            . DIRECTORY_SEPARATOR . "PR_Professors.gif", $filesToInclude[1]["fileReal"]);
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default"
            . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "CreatePR_Professors.gif", $filesToInclude[2]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default" . DIRECTORY_SEPARATOR . "images"
            . DIRECTORY_SEPARATOR . "CreatePR_Professors.gif", $filesToInclude[2]["fileReal"]);

        $this -> assertEquals(2, count($filesToExclude));
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "custom" . DIRECTORY_SEPARATOR . "application"
            . DIRECTORY_SEPARATOR . "Ext" . DIRECTORY_SEPARATOR . "test.php", $filesToExclude[0]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "custom" . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR . "Ext"
            . DIRECTORY_SEPARATOR . "test.php", $filesToExclude[0]["fileReal"]);
        $this -> assertEquals("src" . DIRECTORY_SEPARATOR . "custom" . DIRECTORY_SEPARATOR . "modules" .
            DIRECTORY_SEPARATOR . "test" . DIRECTORY_SEPARATOR . "Ext" . DIRECTORY_SEPARATOR . "excludeme.php",
            $filesToExclude[1]["fileRelative"]);
        $this -> assertEquals("vfs://root" . DIRECTORY_SEPARATOR
            . "src" . DIRECTORY_SEPARATOR . "custom"
            . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "test" . DIRECTORY_SEPARATOR . "Ext"
            . DIRECTORY_SEPARATOR . "excludeme.php", $filesToExclude[1]["fileReal"]);
    }

    public function testOpenZipValidParams(){
        $pg = new PackageGenerator();

        $zip = $pg -> openZip("1", "profM", "pack.php");
        $this -> assertContains('Creating releases/sugarcrm-profM-1.zip', $this -> getActualOutput());
        $this -> assertEquals(0, $zip -> numFiles);
    }

    public function testOpenZipFileAlreadyExists(){
        $root = vfsStream::setup();
        $releasesDirectory = vfsStream::newDirectory("releases") -> at($root);
        vfsStream::newFile("sugarcrm-profM-1.zip") -> at($releasesDirectory);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $this -> expectException(Exception::class);
        $pg -> openZip("1", "profM", "pack.php");
    }

    /*
     * addFile does not work with the urls beginning with "vfs://" so this test does NOT
     * actually test that files were added to the zip.  Instead it tests the output of the
     * function is correct
     */
    public function testAddFilesToZipOneFile(){
        $root = vfsStream::setup();
        $srcDirectory = vfsStream::newDirectory("src") -> at($root);
        vfsStream::newFile("myfile.php") -> at($srcDirectory);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $zip = $pg -> openZip("1", "profM", "pack.php");

        $filesToInclude = array();
        $file = array(
            "fileRelative" => "src" . DIRECTORY_SEPARATOR . "myfile.php",
            "fileReal" =>  "vfs://root" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "myfile.php"
        );
        array_push($filesToInclude, $file);

        $zip = $pg -> addFilesToZip($zip, $filesToInclude);

        $this -> assertContains("[*] src" . DIRECTORY_SEPARATOR . "myfile.php", $this -> getActualOutput());
    }

    /*
     * addFile does not work with the urls beginning with "vfs://" so this test does NOT
     * actually test that files were added to the zip.  Instead it tests the output of the
     * function is correct
     */
    public function testAddFilesToZipNoFiles(){
        $root = vfsStream::setup();

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $zip = $pg -> openZip("1", "profM", "pack.php");

        $filesToInclude = array();
        $outputBeforeAddingFiles = $this -> getActualOutput();

        $zip = $pg -> addFilesToZip($zip, $filesToInclude);

        $outputAfterAddingFiles = $this -> getActualOutput();

        $this -> assertEquals($outputBeforeAddingFiles, $outputAfterAddingFiles);
    }

    /*
     * addFile does not work with the urls beginning with "vfs://" so this test does NOT
     * actually test that files were added to the zip.  Instead it tests the output of the
     * function is correct
     */
    public function testAddFilesToZipMultipleFiles(){
        /*
         * Files to be included:
         *  [*] src/language/application/en_us.lang.php
         *  [*] src/icons/default/images/PR_Professors.gif
         *  [*] src/icons/default/images/CreatePR_Professors.gif
         */

        $root = vfsStream::setup();
        $srcDirectory = vfsStream::newDirectory("src") -> at($root);
        $languageDirectory = vfsStream::newDirectory("language") -> at($srcDirectory);
        $applicationUnderLanguageDirectory = vfsStream::newDirectory("application") -> at($languageDirectory);
        $iconsDirectory = vfsStream::newDirectory("icons") -> at($srcDirectory);
        $defaultDirectory = vfsStream::newDirectory("default") -> at($iconsDirectory);
        $imagesDirectory = vfsStream::newDirectory("images") -> at($defaultDirectory);

        vfsStream::newFile("en_us.lang.php") -> at($applicationUnderLanguageDirectory);
        vfsStream::newFile("PR_Professors.gif") -> at($imagesDirectory);
        vfsStream::newFile("CreatePR_Professors.gif") -> at($imagesDirectory);

        $pg = new PackageGenerator();
        $pg -> setCwd($root -> url());

        $zip = $pg -> openZip("1", "profM", "pack.php");

        $filesToInclude = array();
        $fileEnUs = array(
            "fileRelative" => "src" . DIRECTORY_SEPARATOR . "language" . DIRECTORY_SEPARATOR . "application"
                . DIRECTORY_SEPARATOR . "en_us.lang.php",
            "fileReal" =>  "vfs://root" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "language"
                . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR . "en_us.lang.php"
        );
        $filePRProfessors = array(
            "fileRelative" => "src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default"
                . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "PR_Professors.gif",
            "fileReal" =>  "vfs://root" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "icons"
                . DIRECTORY_SEPARATOR . "default" . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "PR_Professors.gif"
        );
        $fileCreatePRProfessors = array(
            "fileRelative" => "src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default"
                . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "CreatePR_Professors.gif",
            "fileReal" =>  "vfs://root" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "icons"
                . DIRECTORY_SEPARATOR . "default" . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "CreatePR_Professors.gif"
        );
        array_push($filesToInclude, $fileEnUs);
        array_push($filesToInclude, $filePRProfessors);
        array_push($filesToInclude, $fileCreatePRProfessors);

        $zip = $pg -> addFilesToZip($zip, $filesToInclude);

        $this -> assertContains("[*] src" . DIRECTORY_SEPARATOR . "language" . DIRECTORY_SEPARATOR . "application"
            . DIRECTORY_SEPARATOR . "en_us.lang.php", $this -> getActualOutput());
        $this -> assertContains("[*] src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default"
            . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "PR_Professors.gif", $this -> getActualOutput());
        $this -> assertContains("[*] src" . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . "default"
            . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "CreatePR_Professors.gif", $this -> getActualOutput());
    }

}
