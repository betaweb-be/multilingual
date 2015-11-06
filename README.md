# Multilingual Plugin

Adds feature to Pimcore documents to create a solid tree throughout different languages.
Every document will exists in all languages.

## Features

* When creating a new document in a language, it will create a document in all other languages and link them together.
* Ensures the document tree is exactly the same for all languages
* Enables Master Document setting by default to follow the main language (first available language)
* Copies the document properties to all language documents
* Provides helper functions to link to another language
* Pimcore documents are only displayed per language

## Usage

* Enable all languages in system config you require
* Enable and install this plugin

## View helpers

$this->inotherlang($document, $language = null);

Returns the provided document in the provided language.
If no language is provided, the current active language is used.
A Document object or document ID is accepted.