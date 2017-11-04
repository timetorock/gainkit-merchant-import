# gainkit-merchant-import

This script was made for 2 targets:
- Example how to proceed fast gainkit.com price listings
- How fast to:
  - proceed CSV
  - compare with your DB
  - update your DB

And all these less then in a minute for 100k+ lines.

# How to start

- Create account on https://gainkit.com/
- Get your [Merchant account](mailto:support@gainkit.com).
- Get your API key from your Store: Merchant Panel -> Stores.
- clone project
- composer install
- In the file `import.php` add your credentials:

   ```php
    const GAINKIT_API_KEY = 'your api key';

    $config = [
  	'api_key' => GAINKIT_API_KEY,
  	'dbconnection' => 'mysql:host=127.0.0.1;dbname=gainkit_import_test',
  	'dblogin' => 'login',
  	'dbpassword' => 'password',
  ];`
- Create your database and table with script from `database -> database.sql`
- Start script from console: `php -f import.php`
- Customize script for your needs, and good luck! :)

# Requirements

- PHP 7 (Easy switch to PHP 5.6, just remove some PHP7 type returns.)
- MySQL
- [Composer](https://getcomposer.org/)