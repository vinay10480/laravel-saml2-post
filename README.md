## Laravel 5 - Saml2

[![Build Status](https://travis-ci.org/SamlPost/laravel-saml2.svg)](https://travis-ci.org/SamlPost/laravel-saml2)

A Laravel package for Saml2 integration as a SP (service provider) based on  [OneLogin](https://github.com/onelogin/php-saml) toolkit, which is much lighter and easier to install than simplesamlphp SP. It doesn't need separate routes or session storage to work!

The aim of this library is to be as simple as possible. We won't mess with Laravel users, auth, session...  We prefer to limit ourselves to a concrete task. Ask the user to authenticate at the IDP and process the response. Same case for SLO requests.


## Installation - Composer

To install Saml2 as a Composer package to be used with Laravel 5, simply run:

```
composer require aherstein/laravel-saml2-post
```

Once it's installed, you can register the service provider in `config/app.php` in the `providers` array. If you want, you can add the alias saml2:

```php
'providers' => [
        ...
    	SamlPost\Saml2\Saml2ServiceProvider::class,
]

'alias' => [
        ...
        'Saml2' => SamlPost\Saml2\Facades\Saml2Auth::class,
]
```

Then publish the config file with `php artisan vendor:publish`. This will add the file `app/config/saml2_settings.php`. This config is handled almost directly by  [OneLogin](https://github.com/onelogin/php-saml) so you may get further references there, but will cover here what's really necessary. There are some other config about routes you may want to check, they are pretty straightforward.

## Configuration and Setup

### .env file
Most configuration settings are stored in your .env file. See below for required settings:
```
SAML_IDP_HOST=
SAML_IDP_ENTITY_ID=
SAML_IDP_SIGN_ON_URL=
SAML_IDP_SIGN_ON_BINDING=
SAML_IDP_LOG_OUT_URL=
SAML_IDP_LOG_OUT_BINDING=
SAML_IDP_X509CERT=

SAML_SP_X509CERT=
SAML_SP_PRIVATE_KEY=
SAML_SP_NAME_ID_FORMAT=
```

Once you publish your saml2_settings.php to your own files, you need to configure your sp and IDP (remote server). The only real difference between this config and the one that OneLogin uses, is that the SP entityId, assertionConsumerService url and singleLogoutService URL are injected by the library. They are taken from routes 'saml_metadata', 'saml_acs' and 'saml_sls' respectively.

Remember that you don't need to implement those routes, but you'll need to add them to your IDP configuration. For example, if you use simplesamlphp, add the following to /metadata/sp-remote.php

```php
$metadata['http://laravel_url/saml2/metadata'] = array(
    'AssertionConsumerService' => 'http://laravel_url/saml2/acs',
    'SingleLogoutService' => 'http://laravel_url/saml2/sls',
    //the following two affect what the $Saml2user->getUserId() will return
    'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
    'simplesaml.nameidattribute' => 'uid' 
);
```
You can check that metadata if you actually navigate to 'http://laravel_url/saml2/metadata'

Make sure ConfigServiceProvider is properly injecting the correct singleSignOnService url setting for the IDP.

### Authentication Guard

This library supports usage of Laravel's built-in authentication guards.

Add to `app/Providers/AuthServiceProvider.php` `boot()` method:

```php
    public function boot()
    {
        $this->registerPolicies();
        
        Auth::extend('saml', function ($app, $name, array $config) {
            return new Saml2Guard(Auth::createUserProvider($config['provider']));
        });
        
        Auth::provider('samldriver', function ($app, array $config) {
            return new Saml2UserProvider();
        });
```

Add guard configuration to `config/auth.php`

```php
'guards' => [

...

'saml' => [
    'driver' => 'session',
    'provider' => 'samlusers',
],

'providers' => [

...

'samlusers' => [
    'driver' => 'samldriver',
    'model' => SamlPost\Saml2\Saml2User::class,
],
```

### Listeners
Make sure to resgister the login and logout event listener in `app/Providers/EventServiceProvider.php`

```php
    protected $listen = [
        'SamlPost\Saml2\Events\Saml2LoginEvent' => [
            'App\Listeners\LoginListener',
        ],
        'SamlPost\Saml2\Events\Saml2LogoutEvent' => [
            'App\Listeners\LogoutListener',
        ],
    ];
```

## Usage

### Login View

You will need to include the following code in a view called `login`:
```blade
    <form method="post" action="{{$baseUri}}" accept-charset="utf-8">
        <input type="submit" value="Log In via SSO"/>
        @foreach ($samlParameters as $k => $v)
            <input type="hidden" name="{{$k}}" value="{{$v}}"/>
        @endforeach
    </form>
```

To initiate a login, just include all paths you want to protect in the routes file:

```php
Route::middleware(['auth:saml'])->group(function () {
    // Secured routes go here
});
```

The Saml2::login will redirect the user to the IDP and will came back to an endpoint the library serves at /saml2/acs. That will process the response and fire an event when ready. The next step for you is to handle that event by adding a login and logout event listener to `app/Listeners/`:
```php
    public function handle(Saml2LoginEvent $event)
    {
        $user = $event->getSaml2User();
        $auth = $event->getSaml2Auth();

        // Store SAML response data in session
        $this->request->session()->put('isLoggedIn', $auth->isAuthenticated());
        $this->request->session()->put('samlData', $user);
        $this->request->session()->put('user', $user->getAttributes());
    }
```

### Log out
Now there are two ways the user can log out.
 + 1 - By logging out in your app: In this case you 'should' notify the IDP first so it closes global session.
 + 2 - By logging out of the global SSO Session. In this case the IDP will notify you on /saml2/slo endpoint (already provided)

For case 1 call `Saml2Auth::logout();` or redirect the user to the route 'saml_logout' which does just that. Do not close the session inmediately as you need to receive a response confirmation from the IDP (redirection). That response will be handled by the library at /saml2/sls and will fire an event for you to complete the operation.

For case 2 you will only receive the event. Both cases 1 and 2 receive the same event. 

Note that for case 2, you may have to manually save your session to make the logout stick (as the session is saved by middleware, but the OneLogin library will redirect back to your IDP before that happens)

```php
public function handle(Saml2LogoutEvent $event)
{
    // Clear out SAML data from session
    $this->request->session()->put('isLoggedIn', false);
    $this->request->session()->put('samlData', null);
    $this->request->session()->put('user', null);
}
```


### License
Copyright (c) 2017 Adam Herstein

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
