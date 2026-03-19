# Struktal-Core

Core library for the Struktal PHP framework

## Installation

To install this library, include it in your project using Composer:

```bash
composer require struktal/struktal-core
```

## Usage

Start the application by calling the `start` method on the `StruktalCore` class:

```php
\struktal\core\StruktalCore::start(__APP_DIR__, \app\users\User::class);
```

This defines all globals and initializes the application.

## License

This software is licensed under the MIT license.
See the [LICENSE](LICENSE) file for more information.
