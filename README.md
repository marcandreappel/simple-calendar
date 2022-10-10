# Simple PHP Calendar

[![Latest Stable Version](https://poser.pugx.org/marcandreappel/simple-calendar/version)](https://packagist.org/packages/marcandreappel/simple-calendar)
[![License](https://poser.pugx.org/marcandreappel/simple-calendar/license)](https://packagist.org/packages/marcandreappel/simple-calendar)
[![Build Status](https://travis-ci.org/marcandreappel/simple-calendar.svg?branch=master)](https://travis-ci.org/marcandreappel/simple-calendar)


A very simple, easy to use PHP calendar rendering class.

## Requirements

- **php**: >= 8.0

## Installing

Install the latest version with:

```bash
composer require 'marcandreappel/simple-calendar'
```

## Examples

```php
<?php
require '../vendor/autoload.php';

$calendar = new MarcAndreAppel\SimpleCalendar\SimpleCalendar('June 2023');

echo $calendar->render();

```

```php
<?php
require '../vendor/autoload.php';

$calendar = new MarcAndreAppel\SimpleCalendar\SimpleCalendar();

$calendar->addEvent('Sample Event', 'today', 'tomorrow');

$calendar->setWeekdays(['Sun', 'Mon', 'Tu', 'W', 'Th', 'F', 'Sa']);
$calendar->setWeekOffset('mon');

echo $calendar->render();
```
