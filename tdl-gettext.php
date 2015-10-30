<?php
use Gettext\Translations;
define('UTF8_BOM', "\xEF\xBB\xBF");

try {
    checkDependencies();
    $options = readOptions();
    $command = parseCommandLine($arguments);
    switch (strtolower($command)) {
        case '':
            echo "This is a little tool to upload to Transifex the strings to be translated,\nas well as to download translated strings from Transifex.\n";
            echo "\n";
            echo "# To upload a .csv file containing all the translatable English strings:\n";
            echo "  php {$argv[0]} upload [EnglishCsv]\n";
            echo "  Where [EnglishCsv] is the path to a local file (or a remote URL) containing the English strings.\n";
            echo "  If it is not specified, we'll use the ENGLISH_FILE option specified in the configuration file.\n";
            echo "\n";
            echo "# Download the transactions and save them in CSV format:\n";
            echo "  php {$argv[0]} download [DestinationDir]\n";
            echo "  Where [DestinationDir] is the path where the language files will be saved.\n";
            echo "  If it is not specified, we'll use the TRANSLATIONS_DIR option specified in the configuration file.\n";
            break;
        case 'upload':
            switch (count($arguments)) {
                case 0:
                    if (!isset($options['ENGLISH_FILE']) || !is_string($options['ENGLISH_FILE']) || $options['ENGLISH_FILE'] === '') {
                        throw new Exception("Missing English CSV file path/URL: it's not specified neither in the command line nor in the options file.");
                    }
                    $csvFile = $options['ENGLISH_FILE'];
                    break;
                case 1:
                    $csvFile = $arguments[0];
                    break;
                default:
                    throw new Exception("Too many arguments");
            }
            if (preg_match('_^\w+://.+_', $csvFile)) {
                echo "Downloading remote English file... ";
                $fd = @fopen($csvFile, 'rb');
                if (!$fd) {
                    throw new Exception("Failed to start downloading $csvFile");
                }
                $contents = '';
                while(!@feof($fd)) {
                    $chunk = @fread($fd, 8192);
                    if ($chunk === false) {
                        @fclose($fd);
                        throw new Exception("Failed to retrieve the content of $csvFile");
                    }
                    $contents .= $chunk;
                }
                @fclose($fd);
                if ($contents === '') {
                    throw new Exception("Failed to download $csvFile");
                }
                echo "done.\n";
            } else {
                echo "Reading local English file... ";
                if (!is_file($csvFile)) {
                    throw new Exception("Unable to find the file $csvFile");
                }
                $contents = @file_get_contents($csvFile);
                if ($contents === false || $contents === '') {
                    throw new Exception("Failed to read the file $csvFile");
                }
                echo "done.\n";
            }
            echo "Parsing CSV file... ";
            $translations = CsvToGettext($contents, true);
            echo "done.\n";
            $txUsername = (isset($options['TRANSIFEX_USERNAME']) && is_string($options['TRANSIFEX_USERNAME'])) ? $options['TRANSIFEX_USERNAME'] : '';
            if ($txUsername === '') {
                throw new Exception('Missing/invalid TRANSIFEX_USERNAME value in options');
            }
            $txPassword = (isset($options['TRANSIFEX_PASSWORD']) && is_string($options['TRANSIFEX_PASSWORD'])) ? $options['TRANSIFEX_PASSWORD'] : '';
            if ($txPassword === '') {
                throw new Exception('Missing/invalid TRANSIFEX_PASSWORD value in options');
            }
            $txProject = (isset($options['TRANSIFEX_PROJECT']) && is_string($options['TRANSIFEX_PROJECT'])) ? $options['TRANSIFEX_PROJECT'] : '';
            if(!preg_match('#^[\w\-]+$#', $txProject)) {
                throw new Exception('Missing/invalid TRANSIFEX_PROJECT value in options');
            }
            $txResource = (isset($options['TRANSIFEX_RESOURCE']) && is_string($options['TRANSIFEX_RESOURCE'])) ? $options['TRANSIFEX_RESOURCE'] : '';
            if(!preg_match('#^[\w\-]+$#', $txResource)) {
                throw new Exception('Missing/invalid TRANSIFEX_RESOURCE value in options');
            }
            echo "Uploading the new translatable strings to Transifex ($txProject/$txResource)... ";
            $json = @json_encode(array('content' => $translations->toPoString()));
            if ($json === false) {
                throw new Exception("Failed to serialize the translations");
            }
            $curl = new Curl("http://www.transifex.com/api/2/project/$txProject/resource/$txResource/content/");
            $curl->setOpt(CURLOPT_USERPWD, $txUsername.':'.$txPassword);
            $curl->setOpt(CURLOPT_POST, true);
            $curl->setOpt(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $curl->setOpt(CURLOPT_POSTFIELDS, $json);
            $curl->setOpt(CURLOPT_CUSTOMREQUEST, 'PUT');
            $response = $curl->exec();
            if ($response['info']['http_code'] < 200 || $response['info']['http_code'] >= 300) {
                throw new Exception($response['body'] ? $response['body'] : "Transifex returned the error code {$response['info']['http_code']}");
            }
            echo "done.\n";
            echo "Response from Transifex:\n";
            echo $response['body'];
            break;
        case 'download':
            switch (count($arguments)) {
                case 0:
                    if (!isset($options['TRANSLATIONS_DIR']) || !is_string($options['TRANSLATIONS_DIR']) || $options['TRANSLATIONS_DIR'] === '') {
                        throw new Exception("Missing path to the directory where the translations will be saved: it's not specified neither in the command line nor in the options file.");
                    }
                    $saveDir = $options['TRANSLATIONS_DIR'];
                    break;
                case 1:
                    $saveDir = $arguments[0];
                    break;
                default:
                    throw new Exception("Too many arguments");
            }
            $saveDir = rtrim($saveDir, '/\\');
            if (!is_dir($saveDir)) {
                @mkdir($saveDir, 0777, true);
                if (!is_dir($saveDir)) {
                    throw new Exception("Unable to create directory $saveDir");
                }
            }
            echo "Listing languages available on Transifex... ";
            $txUsername = (isset($options['TRANSIFEX_USERNAME']) && is_string($options['TRANSIFEX_USERNAME'])) ? $options['TRANSIFEX_USERNAME'] : '';
            if ($txUsername === '') {
                throw new Exception('Missing/invalid TRANSIFEX_USERNAME value in options');
            }
            $txPassword = (isset($options['TRANSIFEX_PASSWORD']) && is_string($options['TRANSIFEX_PASSWORD'])) ? $options['TRANSIFEX_PASSWORD'] : '';
            if ($txPassword === '') {
                throw new Exception('Missing/invalid TRANSIFEX_PASSWORD value in options');
            }
            $txProject = (isset($options['TRANSIFEX_PROJECT']) && is_string($options['TRANSIFEX_PROJECT'])) ? $options['TRANSIFEX_PROJECT'] : '';
            if(!preg_match('#^[\w\-]+$#', $txProject)) {
                throw new Exception('Missing/invalid TRANSIFEX_PROJECT value in options');
            }
            $txResource = (isset($options['TRANSIFEX_RESOURCE']) && is_string($options['TRANSIFEX_RESOURCE'])) ? $options['TRANSIFEX_RESOURCE'] : '';
            if(!preg_match('#^[\w\-]+$#', $txResource)) {
                throw new Exception('Missing/invalid TRANSIFEX_RESOURCE value in options');
            }
            $curl = new Curl("http://www.transifex.com/api/2/project/$txProject/resource/$txResource/?details");
            $curl->setOpt(CURLOPT_USERPWD, $txUsername.':'.$txPassword);
            /*
            $response = $curl->exec();
            if ($response['info']['http_code'] < 200 || $response['info']['http_code'] >= 300) {
                throw new Exception($response['body'] ? $response['body'] : "Transifex returned the error code {$response['info']['http_code']}");
            }
            */
            $response = array('body' => <<<EOT
{
    "source_language_code": "en",
    "name": "core", 
    "created": "2015-10-28T12:59:30.901", 
    "wordcount": 8254, 
    "i18n_type": "PO", 
    "project_slug": "tdl-test", 
    "accept_translations": true, 
    "last_update": "2015-10-28T16:34:46.656", 
    "priority": "0", 
    "available_languages": [
        {
            "code_aliases": " ", 
            "code": "zh-Hans", 
            "name": "Chinese Simplified"
        }, 
        {
            "code_aliases": " nl-BE ", 
            "code": "nl_BE", 
            "name": "Dutch (Belgium)"
        }, 
        {
            "code_aliases": " ", 
            "code": "en", 
            "name": "English"
        }, 
        {
            "code_aliases": " fr-FR fr-fr fr_fr ", 
            "code": "fr_FR", 
            "name": "French (France)"
        }, 
        {
            "code_aliases": " de-DE de-de de_de  ", 
            "code": "de_DE", 
            "name": "German (Germany)"
        }, 
        {
            "code_aliases": " he-IL he-il ", 
            "code": "he_IL", 
            "name": "Hebrew (Israel)"
        }, 
        {
            "code_aliases": " it-IT it-it ", 
            "code": "it_IT", 
            "name": "Italian (Italy)"
        }, 
        {
            "code_aliases": " ja-JP ja-jp ", 
            "code": "ja_JP", 
            "name": "Japanese (Japan)"
        }, 
        {
            "code_aliases": " ru-RU ru-ru ", 
            "code": "ru_RU", 
            "name": "Russian (Russia)"
        }, 
        {
            "code_aliases": " sk-SK sk-sk ", 
            "code": "sk_SK", 
            "name": "Slovak (Slovakia)"
        }, 
        {
            "code_aliases": " es-ES es_es ", 
            "code": "es_ES", 
            "name": "Spanish (Spain)"
        }
    ], 
    "total_entities": 2233, 
    "slug": "core", 
    "categories": null
}
EOT
            );
            $info = @json_decode($response['body'], true);
            $invalidResponse = false;
            if (!is_array($info)) {
                $invalidResponse = true;
            }
            else {
                if (!isset($info['source_language_code']) || !is_string($info['source_language_code']) || $info['source_language_code'] === '') {
                    $invalidResponse = true;
                } else {
                    $sourceLanguageCode = $info['source_language_code'];
                    if (!isset($info['available_languages']) || !is_array($info['available_languages'])) {
                        $invalidResponse = true;
                    } else {
                        $languages = array();
                        foreach ($info['available_languages'] as $availableLanguage) {
                            if (
                                !is_array($availableLanguage)
                                || !isset($availableLanguage['code']) || !is_string($availableLanguage['code']) || $availableLanguage['code'] === ''
                                || !isset($availableLanguage['name']) || !is_string($availableLanguage['name']) || $availableLanguage['name'] === ''
                            ) {
                                $invalidResponse = true;
                                break;
                            }
                            if ($availableLanguage['code'] !== $sourceLanguageCode) {
                                $languages[$availableLanguage['code']] = $availableLanguage['name'];
                            }
                        }
                        if (empty($languages)) {
                            $invalidResponse = true;
                        }
                    }
                }
            }
            if ($invalidResponse) {
                throw new Exception("Invalid response from Transifex:\n{$response['body']}");
            }
            echo count($languages)." languages found.\n";
            foreach ($languages as $languageID => $languageName) {
                echo "Working on $languageName\n";
                echo "  - downloading... ";
                $curl = new Curl("http://www.transifex.com/api/2/project/$txProject/resource/$txResource/translation/$languageID/");
                $curl->setOpt(CURLOPT_USERPWD, $txUsername.':'.$txPassword);
                $curl->setOpt(CURLOPT_CUSTOMREQUEST, 'GET');                
                $response = $curl->exec();
                if ($response['info']['http_code'] < 200 || $response['info']['http_code'] >= 300) {
                    throw new Exception($response['body'] ? $response['body'] : "Transifex returned the error code {$response['info']['http_code']}");
                }
                $contents = @json_decode($response['body'], true);
                $invalidResponse = false;
                if (!is_array($contents) || !isset($contents['content']) || !is_string($contents['content']) || $contents['content'] === '') {
                    throw new Exception("Invalid response from Transifex:\n{$response['body']}");
                }
                echo "done.\n";
                echo "  - parsing downloaded translations... ";
                $translations = Gettext\Translations::fromPoString($contents['content']);
                if (empty($translations)) {
                    throw new Exception('no translations found!');
                }
                echo "done.\n";
                echo "  - converting translations to csv... ";
                $csv = GettextToCsv($translations, $options);
                echo "done.\n";
                $saveTo = $saveDir.DIRECTORY_SEPARATOR.$languageName.'.csv';
                echo "  - saving to $saveTo... ";
                if (@file_put_contents($saveTo, $csv) === false) {
                    throw new Exception("Unable to write CSV file $saveTo");
                }
                echo "done.\n";
            }
            break;
        default:
            throw new Exception("Unknown command: $command");
    }
    exit(0);
}
catch (Exception $x) {
    $fd = fopen('php://stderr', 'a');
    fwrite($fd, $x->getMessage());
    fclose($fd);
    exit(1);
}

/**
 * Check the availability of the required libraries.
 * Throws an Exception in case of problems.
 *
 * @throws Exception
 */
function checkDependencies()
{
    if (!function_exists('json_decode')) {
        throw new Exception('Missing JSON support in your PHP installation');
    }
    $vendorAutoloader = dirname(__FILE__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
    if (!is_file($vendorAutoloader)) {
        throw new Exception("In order to run this tool, you need to install Composer (https://getcomposer.org) and run this command:\ncomposer install --working-dir ".escapeshellarg(dirname(__FILE__)));
    }
    require_once $vendorAutoloader;
    require_once __DIR__.'/inc/WindowsLocales.php';
    require_once __DIR__.'/inc/Curl.php';
}

/**
 * Read the program options.
 * In case of errors an Exception will be thrown.
 * 
 * @return array
 * 
 * @throws Exception
 */
function readOptions()
{
    $optionsFile = dirname(__FILE__).DIRECTORY_SEPARATOR.basename(__FILE__, '.php').'.config.php';
    if (!is_file($optionsFile)) {
        echo "The configuration file\n$optionsFile\ndoes not exits.\nDo you want to create a default one? ";
        $createEmptyConfig = null;
        for (;;) {
            $s = @fgets(STDIN);
            if (!is_string($s)) {
                break;
            }
            $s = strtolower(trim($s));
            switch ($s) {
                case 'y':
                case 'yes':
                case '1':
                    $createEmptyConfig = true;
                    break;
                case 'n':
                case 'no':
                case '0':
                    $createEmptyConfig = false;
                    break;
            }
            if (isset($createEmptyConfig)) {
                break;
            }
            echo "Please answer with Y[es] or N[O]... ";
        }
        if ($createEmptyConfig === true) {
            $config = array();
            $config[] = "<?php";
            $config[] = "return array(";
            $config[] = '';
            $config[] = "    // The location of the TodoList file that contains the strings to be translated.";
            $config[] = "    // It can be a local file or an URL";
            $config[] = "    'ENGLISH_FILE' => 'https://raw.githubusercontent.com/abstractspoon/ToDoList_Resources/master/Translations/YourLanguage.csv',";
            $config[] = '';
            $config[] = "    // The location where the CSV language files will be saved.";
            $config[] = "    'TRANSLATIONS_DIR' => '".addslashes(dirname(__FILE__).DIRECTORY_SEPARATOR.'translations')."',";
            $config[] = '';
            $config[] = "    // The value of the TRANSTEXT Library";
            $config[] = "    'TRANSTEXT_VERSION' => '7.0.0.0',";
            $config[] = '';
            $config[] = "    // Your Transifex user name";
            $config[] = "    'TRANSIFEX_USERNAME' => 'username',";
            $config[] = '';
            $config[] = "    // Your Transifex password";
            $config[] = "    'TRANSIFEX_PASSWORD' => 'password',";
            $config[] = '';
            $config[] = "    // The Transifex project URL slug";
            $config[] = "    'TRANSIFEX_PROJECT' => 'tdl-test',";
            $config[] = '';
            $config[] = "    // The Transifex resource URL slug";
            $config[] = "    'TRANSIFEX_RESOURCE' => 'core',";
            $config[] = '';
            $config[] = ");";
            if (@file_put_contents($optionsFile, implode(PHP_EOL, $config).PHP_EOL) === false) {
                throw new Exception('Unable to create the configuration file.');
            }
            throw new Exception("The configuration file has been created.\nPlease customize it as for your needings.");
        }
        else {
            throw new Exception("Please create your configuration file.");
        }
    }
    $options = null;
    $options = include $optionsFile."";
    if (!is_array($options)) {
        throw new Exception("Invalid configuration file\n$optionsFile");
    }
    return $options;
}

/**
 * 
 */
function parseCommandLine(&$arguments)
{
    $command = '';
    $arguments = array();
    global $argv;
    if (isset($argv) && is_array($argv) && count($argv) > 1) {
        $command = $argv[1];
        $arguments = array_slice($argv, 2);
        switch ($command) {
            case 'h':
            case 'help':
            case '/?':
            case '?':
            case '-h':
            case '--help':
                $command = '';
                break;
        }
    }
    return $command;
}

/**
 * Execute a command and return its output.
 *
 * @param string $command The command to execute
 * @param string|array $arguments The command arguments
 * @param int|int[] $goodResult The exit code(s) to be considered as good. In case the command ends with a different exit code, an Exception will be thrown
 * @param int $exitCode The command exit code will be put here
 *
 * @return string Returns the command output
 *
 * @throws Exception
 */
function run($command, $arguments = '', $goodExitCode = 0, &$exitCode = null) {
    $line = escapeshellarg($command);
    if(is_array($arguments)) {
        if(count($arguments)) {
            $line .= ' ' . implode(' ', $arguments);
        }
    }
    else {
        $arguments = (string)$arguments;
        if($arguments !== '') {
            $line .= ' ' . $arguments;
        }
    }
    $output = array();
    @exec($line . ' 2>&1', $output, $exitCode);
    if(!@is_int($exitCode)) {
        $exitCode = -1;
    }
    if (!is_array($output)) {
        $output = array();
    }
    $failed = false;
    if (is_array($goodExitCode)) {
        if (in_array($exitCode, $goodExitCode) !== true) {
            $failed = true;
        }
    }
    elseif(is_int($goodExitCode) || (is_string($goodExitCode) && is_numeric($goodExitCode))) {
        $goodExitCode = (int) $goodExitCode;
        if ($exitCode !== $goodExitCode) {
            $failed = true;
        }
    }
    if ($failed) {
        throw new Exception("$command failed: " . implode("\n", $output));
    }

    return implode("\n", $output);
}

/**
 * Reads a CSV language file.
 * @param string $csv
 * @param bool $isSourceLanguage
 * @return Gettext\Translations
 * @throws Exception
 */
function CsvToGettext($csv, $isSourceLanguage = false)
{
    $translations = new Gettext\Translations();
    //$translations->setLanguage($language->id);
    $charMap = array(
        "\\t" => "\t",
        "\\r" => "\r",
        "\\n" => "\n",
    );
    if (strpos($csv, UTF8_BOM) === 0) {
        $csv = substr($csv, strlen(UTF8_BOM));
    }
    $primaryLangID = null;
    foreach (explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $csv))) as $lineIndex => $lineOriginal) {
        $line = trim($lineOriginal);
        if ($line === '') {
            continue;
        }
        $invalidLine = false;
        if ($line[0] === '"') {
            $chunks = explode("\t", $line);
            if (count($chunks) !== 3) {
                $invalidLine = true;
            }
            else {
                $classID = $textOut = $textIn = '';
                foreach ($chunks as $chunkIndex => $chunk) {
                    $len = strlen($chunk);
                    if ($len < 2 || $chunk[0] !== '"'  || $chunk[$len - 1] !== '"' ) {
                        $invalidLine = true;
                        break;
                    }
                    $value = ($len === 2) ? '' : strtr(substr($chunk, 1, -1), $charMap);
                    switch ($chunkIndex) {
                        case 0:
                            $textIn = $value;
                            break;
                        case 1:
                            if (!$isSourceLanguage) {
                                $textOut = $value;
                            }
                            break;
                        case 2:
                            $classID = $value;
                            break;
                    }
                }
                if (!$invalidLine) {
                    $already = $translations->find($classID, $textIn);
                    if ($already === false) {
                        $translation = $translations->insert($classID, $textIn);
                        $translation->setTranslation($textOut);
                        if (
                            preg_match('/(^|[^%])%[dscf]/', $textIn)
                            ||
                            preg_match('/(^|[^%])%(\d*\.\d+)f/', $textIn)
                        ) {
                            $translation->addFlag('c-format');
                        }
                    } elseif (strlen($textOut) > strlen($already->getTranslation())) {
                        $already->setTranslation($textOut);
                    }
                }
            }
        }
        elseif (preg_match('/^PRIMARY_LANGID\s+(\d+)$/', $line, $matches)) {
            if (isset($primaryLangID)) {
                throw new Exception('Duplicated PRIMARY_LANGID found at line '.($lineIndex + 1));
            }
            $primaryLangID = (int) $matches[1];
        }
        else {
            $invalidLine = true;
            if (
                preg_match('/^TRANSTEXT\s+\d[\d\.]*$/', $line)
                ||
                preg_match('/^English\s+Text\s+Translated\s+Text\s+Item\s+Type$/', $line)
                ||
                preg_match('/^NEED_TRANSLATION$/', $line)
                ||
                preg_match('/^TRANSLATED$/', $line)
            ) {
                $invalidLine = false;
            }
        }
        if ($invalidLine) {
            throw new Exception("Bad line ".($lineIndex + 1)." found in language file:\n".$lineOriginal);
        }
    }
    if (!isset($primaryLangID)) {
        throw new Exception('Missing PRIMARY_LANGID');
    }
    $langISO = null;
    foreach (WindowsLocales::$primaryLanguages as $iso => $langID) {
        if ($langID === $primaryLangID) {
            $langISO = $iso;
            break;
        }
    }
    if (!isset($langISO)) {
        throw new Exception('Invalid PRIMARY_LANGID: '.$primaryLangID);
    }
    $translations->setLanguage($langISO);
    return $translations;
}

function GettextToCsv($translations, $options)
{
    /** @var Gettext\Translations $translations */
    $csv = array();
    if (!isset($options['TRANSTEXT_VERSION']) || !is_string($options['TRANSTEXT_VERSION']) || !preg_match('/^\d+(\.\d+)*$/', $options['TRANSTEXT_VERSION'])) {
        throw new Exception('Missing/invalid TRANSTEXT_VERSION value in options');
    }
    $csv[] = 'TRANSTEXT '.$options['TRANSTEXT_VERSION'];
    $iso = $translations->getLanguage();
    if (!isset($iso)) {
        throw new Exception('No language in gettext');
    }
    $primaryLangID = null;
    if (isset(WindowsLocales::$primaryLanguages[$iso])) {
        $primaryLangID = WindowsLocales::$primaryLanguages[$iso];
    }
    else {
        $chunks = explode('_', str_replace('-', '_', $iso));
        for ($i = count($chunks) - 1; $i >= 1; $i++) {
            $isoMain = implode('_', array_slice($chunks, 0, $i));
            if (isset(WindowsLocales::$primaryLanguages[$isoMain])) {
                $primaryLangID = WindowsLocales::$primaryLanguages[$isoMain];
                break;
            }
        }
    }
    if (!isset($primaryLangID)) {
        throw new Exception('Unknown language in gettext: '.$iso);
    }
    $csv[] = 'PRIMARY_LANGID '.$primaryLangID;
    $csv[] = "English Text\tTranslated Text\tItem Type";
    $needTranslation = array();
    $translated = array();
    $charMap = array(
        "\t" => "\\t",
        "\r" => "\\r",
        "\n" => "\\n",
    );
    foreach ($translations as $translation) {
        /** @var Gettext\Translation $translation */
        $serialized = "\"".strtr($translation->getOriginal(), $charMap)."\"\t\"".strtr($translation->getTranslation(), $charMap)."\"\t\"".strtr($translation->getContext(), $charMap)."\"";
        if ($translation->hasTranslation()) {
            if (empty($translated)) {
                $translated[] = 'TRANSLATED';
            }
            $translated[] = $serialized;
        } else {
            if (empty($needTranslation)) {
                $needTranslation[] = 'NEED_TRANSLATION';
            }
            $needTranslation[] = $serialized;
        }
    }
    $csv = array_merge($csv, $needTranslation, $translated);

    return UTF8_BOM.implode("\r\n", $csv)."\r\n";
}
