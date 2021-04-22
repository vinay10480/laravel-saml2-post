<?php

namespace SamlPost\Saml2\Events;

use SamlPost\Saml2\Saml2User;
use SamlPost\Saml2\Saml2Auth;

class Saml2LoginEvent {

    protected $user;
    protected $auth;

    function __construct(Saml2User $user, Saml2Auth $auth)
    {
        $this->user = $user;
        $this->auth = $auth;
    }

    public function getSaml2User()
    {
        return $this->user;
    }

    public function getSaml2Auth()
    {
        return $this->auth;
    }

}
