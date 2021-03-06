<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit31b4e167450311632267b02b6fdaa9a9
{
    public static $prefixLengthsPsr4 = array (
        'Y' => 
        array (
            'YaMoney\\CodeGeneratorBundle\\' => 28,
            'YaMoney\\' => 8,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'YaMoney\\CodeGeneratorBundle\\' => 
        array (
            0 => __DIR__ . '/..' . '/yandex-money/yandex-money-sdk-php/code-generator/CodeGeneratorBundle',
        ),
        'YaMoney\\' => 
        array (
            0 => __DIR__ . '/..' . '/yandex-money/yandex-money-sdk-php/lib',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit31b4e167450311632267b02b6fdaa9a9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit31b4e167450311632267b02b6fdaa9a9::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
