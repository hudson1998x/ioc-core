<?php

    namespace Hudsxn\IocCore\Internals;

    use ReflectionClass;
    use ReflectionProperty;
    use DateTime;

    class Deserializer
    {
        private ReflectionMap $reflectionMap;

        public function __construct(ReflectionMap $reflectionMap)
        {
            $this->reflectionMap = $reflectionMap;
        }

        public function deserialize(string $className, array $data)
        {
            $reflectionClass = $this->reflectionMap->getClass($className);
            $instance = $reflectionClass->newInstance();

            foreach ($reflectionClass->getProperties() as $property) {
                $propertyName = $property->getName();

                // Convert Pascal case property name to snake case
                $snakeCaseName = $this->snakeToPascal($propertyName);

                if (array_key_exists($snakeCaseName, $data)) {
                    $this->setPropertyValue($instance, $property, $data[$snakeCaseName]);
                }
            }

            return $instance;
        }

        private function setPropertyValue($instance, ReflectionProperty $property, $value)
        {
            $propertyType = $property->getType();

            if ($propertyType !== null) {
                $typeName = $propertyType->getName();

                switch ($typeName) {
                    case 'int':
                        $value = (int)$value;
                        break;
                    case 'float':
                        $value = (float)$value;
                        break;
                    case 'bool':
                        $value = (bool)$value;
                        break;
                    case 'string':
                        $value = (string)$value;
                        break;
                    case 'array':
                        $value = $this->handleArray($value);
                        break;
                    case DateTime::class:
                        $value = new DateTime($value);
                        break;
                    default:
                        // Handle other core PHP classes and user-defined classes
                        if (class_exists($typeName)) {
                            $value = $this->deserialize($typeName, (array)$value);
                        }
                        break;
                }
            }

            $instance->{$property->getName()} = $value;
        }

        private function handleArray(array $array)
        {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $array[$key] = $this->handleArray($value);
                } elseif (is_object($value)) {
                    $className = get_class($value);
                    if (class_exists($className)) {
                        $array[$key] = $this->deserialize($className, (array)$value);
                    }
                }
            }

            return $array;
        }

        public function snakeToPascal( string $input ): string
        {
            return str_replace( 
                ' ', 
                '', 
                ucwords(
                    str_replace(
                        '_', 
                        ' ', 
                        strtolower( $input )
                    )
                )
            );
        }
    }
