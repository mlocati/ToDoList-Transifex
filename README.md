# ToDoList-Transifex

A command-line tool written in PHP to handle [ToDoList](http://www.codeproject.com/Articles/5371/ToDoList) translations.

## Requirements

You need PHP 5.3+ with the `php_curl` extension.

## Configuration

First of all, you need to create a configuration file.
In order to do that automatically, simply run:

```sh
php tdl-gettext.php
```

The program will see that a configuration file does not exist, and ask you if you want to create one.
Answer yes.

Then open the newly created file (`tdl-gettext.config.php`) and customize it.
Please remark that you'll need a (free) account on Transifex.

## Usage

### Uploading the English strings to be translated to Transifex

Simply run
```sh
php tdl-gettext.php upload <PathToTheEnglishCsvFile>
```
The program will load the specified CSV file (which can be a local file or a remote URL), will convert it in a format acceptable by Transifex (gettext .pot file), and will update the Transifex resources that translators will translate.

Here's a sample session:

```sh
> php tdl-gettext.php upload Path\To\YourLanguage.csv
Reading local English file... done.
Parsing CSV file... done.
Uploading the new translatable strings to Transifex... done.
Response from Transifex:
{
    "strings_added": 123,
    "strings_updated": 456,
    "strings_delete": 0
}
```

### Downloading translated files from Transifex

The translators will translate strings on Transifex.
In order to download those translated strings, simply run:
```sh
php tdl-gettext.php download <WhereToSaveTheCsvFiles>
```
The program will list the languages available on Transifex, it will download all those translations, convert them in CSV format and save them in the folder specified by `<WhereToSaveTheCsvFiles>`.

Here's a sample session:

```sh
>php tdl-gettext.php download translations
Listing languages available on Transifex... 10 languages found.
Working on Chinese Simplified
  - downloading... done.
  - parsing downloaded translations... done.
  - converting translations to csv... done.
  - saving to translations\Chinese Simplified.csv... done.

...

Working on Spanish (Spain)
  - downloading... done.
  - parsing downloaded translations... done.
  - converting translations to csv... done.
  - saving to translations\Spanish (Spain).csv... done.

```

### Sample Transifex session

![Transifex sample session](https://raw.githubusercontent.com/mlocati/ToDoList-Transifex/master/images/transifex-example.gif)