<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1bdb1d25839de68e46281605ccc94c30
{
    public static $prefixLengthsPsr4 = array (
        'c' => 
        array (
            'calderawp\\licensing_helper\\' => 27,
        ),
        'S' => 
        array (
            'Stripe\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'calderawp\\licensing_helper\\' => 
        array (
            0 => __DIR__ . '/..' . '/calderawp/licensing-helper/src',
        ),
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1bdb1d25839de68e46281605ccc94c30::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1bdb1d25839de68e46281605ccc94c30::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}