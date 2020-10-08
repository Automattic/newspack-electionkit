# newspack-electionkit

## Includes:
* Sample Ballot Tool - 

Install using a shortcode ```[sample_ballot]```

Has a dependency on Google API key for address.  Later versions will make it a WordPress option.

## API Keys:

This plugin requires a valid Google Maps Geocoding API key. You can obtain one for free following the instructions from [Google here](https://developers.google.com/maps/documentation/geocoding/start).

Please add a line to `wp-config.php` providing this key, as follows:

```
define( 'NEWSPACK_ELECTIONKIT_GOOGLE_API_KEY', 'YOUR-GOOGLE-API-KEY' );
```

## Development

Run `composer update && npm install`.

Run `npm run build`.