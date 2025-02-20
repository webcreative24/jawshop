<?php
/**
 * @copyright: Copyright © 2023 Firebear Studio. All rights reserved.
 * @author   : Firebear Studio <fbeardev@gmail.com>
 */
declare(strict_types=1);

namespace Firebear\ImportExport\Model;

use Laminas\Validator\ValidatorChain as LaminasValidatorChain;
use Laminas\Validator\ValidatorInterface;
use ReflectionException;
use Firebear\ImportExport\Model\ValidatorChain\ValidateException;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class ValidatorChain extends LaminasValidatorChain
{
    /**
     * Method to validate a class have value.
     *
     * @param mixed  $value
     * @param string $classBaseName
     * @param array  $args
     *
     * @return boolean
     * @throws ValidateException
     */
    public static function is($value, $classBaseName, array $args = [])
    {
        try {
            $class = new \ReflectionClass($classBaseName);

            if ($class->implementsInterface(ValidatorInterface::class)) {
                if ($class->hasMethod('__construct')) {
                    $keys = array_keys($args);
                    $numeric = false;

                    foreach ($keys as $key) {
                        if (is_numeric($key)) {
                            $numeric = true;
                            break;
                        }
                    }
                    if ($numeric) {
                        $object = $class->newInstanceArgs($args);
                    } else {
                        $object = $class->newInstance($args);
                    }
                } else {
                    $object = $class->newInstance();
                }

                return $object->isValid($value);
            }
        } catch (ReflectionException $exception) {
            throw new ValidateException($exception->getMessage());
        }

        throw new ValidateException("Validate class not found from basename '$classBaseName'");
    }
}
