<?php

namespace Metaclass\FilterBundle\Tests\Utils;

class Reflection
{
    /** @throws \ReflectionException */
    public static function getProperty($objectOrClass, $name)
    {
        $rp = new \ReflectionProperty($objectOrClass, $name);
        $rp->setAccessible(true);

        return is_string($objectOrClass)
            ? $rp->getValue()
            : $rp->getValue($objectOrClass);
    }

    /** @throws \ReflectionException */
    public static function setProperty($objectOrClass, $name, $value): void
    {
        $rp = new \ReflectionProperty($objectOrClass, $name);
        $rp->setAccessible(true);

        if (is_string($objectOrClass)) {
            $rp->setValue($value);
        } else {
            $rp->setValue($objectOrClass, $value);
        }
    }

    /** @throws \ReflectionException */
    public static function newWithId($class, $id)
    {
        $result = new $class();
        self::setProperty($result, 'id', $id);
        return $result;
    }

    public static function callMethod($classOrObject, string $method, $args=[])
    {
        $rm = new \ReflectionMethod($classOrObject, $method);
        $rm->setAccessible(true);

        return $rm->invokeArgs(
            (is_string($classOrObject) ? null : $classOrObject),
            $args
        );
    }

}
