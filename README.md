# Markdown To EditorJS Block \[Under Development\]

PHP Library to convert markdown file content to block based content of [editorjs](https://github.com/codex-team/editor.js).

This library is not parsed the header of markdown file (front-matter/yaml) so use another tools such as [Symfony YAML](https://symfony.com/doc/current/components/yaml.html).

This library is currently under development!

## Supported Blocks

- Code
- Header
- List (Ordered and Unordered)
- Image
- Table
- Paragraph
- Inline Tools : bold, italic, inline code, inline link

## Installation

Coming Soon

## Usage

```php
<?php
require __DIR__.'/vendor/autoload.php' // If using composer
$raw = file_get_contents(__DIR__.'/example.md');
$mdblock = new Hsnfirdaus\MarkdownEditorjs($raw);
print_r($mdblock->getBlocks());
?>
```