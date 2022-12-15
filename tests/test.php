<?php

use Chaungoclong\Container\Container;
use Chaungoclong\Container\Exceptions\BindingResolutionException;
use Chaungoclong\Container\SubTest;
use Chaungoclong\Container\Test;
use Psr\Container\NotFoundExceptionInterface;

require_once __DIR__ . DIRECTORY_SEPARATOR . '../vendor/autoload.php';

$container = Container::getInstance();
try {
    $container->bind('test', Test::class);
    $container->bind('subTest', SubTest::class);
    $test2 = new Test($container->get('subTest'));
    $test = $container->get('test');
//    var_dump($test === $test2);
    $container->singleton(Test::class);
    $test1 = $container->get(Test::class);
    $test2 = $container->get(Test::class);

    var_dump($test1 === $test2);
} catch (BindingResolutionException|NotFoundExceptionInterface $e) {
    echo($e->getMessage());
} catch (ReflectionException $e) {
    echo($e->getMessage());
}

