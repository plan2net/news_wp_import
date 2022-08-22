# TYPO3 Extension `news_wp_import`

This extension imports WordPress categories & posts into TYPO3's extension EXT:news.

**Be aware**: This extension has been created for a single client and might need further adoption for your project.

## Usage

Install extension by using `composer req studiomitte/news-wp-import`.

### Duplicate DB of WordPress

Use a duplicate of the WordPress and make it available for the TYPO3 installation by defining the configuration in the `LocalConfiguration.php` file:

```php
    'DB' => [
        'Connections' => [
            'Default' => [
                'charset' => 'utf8',
                'dbname' => 'db',
                'driver' => 'mysqli',
                'host' => 'mysql',
                'password' => 'root',
                'port' => 3306,
                'user' => 'root',
            ],
            
            // --- WordPress DB begin
            'wp' => [
                'charset' => 'utf8',
                'dbname' => 'wp',
                'driver' => 'mysqli',
                'host' => 'mysql',
                'password' => 'root',
                'port' => 3306,
                'user' => 'root',
            ],
            // --- WordPress DB end
        ],
    ],
```

### Run import

Use the following command to import: `./typo3cms wp2news:import wp 123`.

Arguments:

1) `wp`: Name of the connection+
2) `123`: Page ID which is used to persist the records 


## Credits
This extension was created by Georg Ringer for [Studio Mitte, Linz](https://studiomitte.com).

[Find more TYPO3 extensions we have developed](https://www.studiomitte.com/loesungen/typo3) that provide additional features for TYPO3 sites. 