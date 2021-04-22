<?php

namespace SamlPost\Saml2;


use Illuminate\Contracts\Auth\Authenticatable;
use SamlPost\Saml2\Facades\Saml2Auth;


class Saml2Guard implements \Illuminate\Contracts\Auth\Guard
{
    protected $request;

    public function __construct(Request $request = null)
    {

        $this->request = $request;
    }

    /**
     * Check if SAML user is logged in
     */
    public function check()
    {
        $isLoggedIn = session('isLoggedIn');
        return !empty($isLoggedIn) && $isLoggedIn; //TODO && $this->isValidGroup(???)
    }

    public function guest()
    {
        $isLoggedIn = session('isLoggedIn');
        if (empty($isLoggedIn)) {
            return true;
        } else {
            return false;
        }
    }

    public function user()
    {
        return session('user');
    }

    public function id()
    {
        //TODO
    }


    public function validate(array $credentials = [])
    {
        return Saml2Auth::login(URL::full());
    }

    public function setUser(Authenticatable $user)
    {
        $this->request->session()->put('user', $user->getAttributes());

    }
}