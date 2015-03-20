<?php

namespace Gini\Module;

class GapperAuthTust
{
    public static function setup()
    {
    }

    public static function diagnose()
    {
        $home = \Gini\Config::get('app.home');
        if (!$home) {
            return ['请配置app.home'];
        }
        $secret = \Gini\Config::get('app.tust_secret');
        if (!$secret) {
            return ['需要提供app.tust_secret'];
        }
    }
}
