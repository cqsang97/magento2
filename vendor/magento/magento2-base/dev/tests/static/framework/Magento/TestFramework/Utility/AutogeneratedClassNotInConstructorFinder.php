<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\TestFramework\Utility;

/**
 * Find classes created by object manager that are not requested in constructor. All autogenerated classes should be
 * requested in constructor, otherwise compiler will not be able to find and generate these classes
 */
class AutogeneratedClassNotInConstructorFinder
{
    /**
     * @var ClassNameExtractor
     */
    private $classNameExtractor;

    /**
     * Constructor
     *
     * @param ClassNameExtractor $classNameExtractor
     */
    public function __construct(
        ClassNameExtractor $classNameExtractor
    ) {
        $this->classNameExtractor = $classNameExtractor;
    }

    /**
     * Find classes created by object manager that are not requested in constructor
     *
     * @param string $fileContent
     * @return array
     */
    public function find($fileContent)
    {
        $classNames = [];

        preg_match_all(
            '/(get|create)\(\s*([a-z0-9\\\\]+)::class\s*\)/im',
            $fileContent,
            $shortNameMatches
        );

        if (isset($shortNameMatches[2])) {
            foreach ($shortNameMatches[2] as $shortName) {
                if (substr($shortName, 0, 1) !== '\\') {
                    $class = $this->matchPartialNamespace($fileContent, $shortName);
                } else {
                    $class = $shortName;
                }
                $class = ltrim($class, '\\');

                if (\Magento\Framework\App\Utility\Classes::isVirtual($class)) {
                    continue;
                }

                $className = $this->classNameExtractor->getNameWithNamespace($fileContent);
                if ($className) {
                    $arguments = $this->getConstructorArguments($className);
                    if (in_array($class, $arguments)) {
                        continue;
                    }
                }

                $classNames[] = $class;
            }
        }
        return $classNames;
    }

    /**
     * Get constructor arguments
     *
     * @param string $className
     * @return string[]
     */
    private function getConstructorArguments($className)
    {
        $arguments = [];
        $reflectionClass = new \ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();
        if ($constructor) {
            $classParameters = $constructor->getParameters();
            foreach ($classParameters as $classParameter) {
                if ($classParameter->getType()) {
                    $parameterType = $classParameter->getType();
                    $arguments[] = ltrim($parameterType, '\\');
                }
            }
        }
        return $arguments;
    }

    /**
     * Match partial namespace
     *
     * @param $fileContent
     * @param $shortName
     * @return string
     */
    private function matchPartialNamespace($fileContent, $shortName)
    {
        preg_match_all(
            '/^use\s([a-z0-9\\\\]+' . str_replace('\\', '\\\\', $shortName) . ');$/im',
            $fileContent,
            $fullNameMatches
        );
        if (isset($fullNameMatches[1][0])) {
            $class = $fullNameMatches[1][0];
        } else {
            preg_match_all(
                '/^use\s([a-z0-9\\\\]+)\sas\s' . str_replace('\\', '\\\\', $shortName) . ';$/im',
                $fileContent,
                $fullNameAliasMatches
            );
            if (isset($fullNameAliasMatches[1][0])) {
                $class = $fullNameAliasMatches[1][0];
            } else {
                $forwardSlashPos = strpos($shortName, '\\');
                $partialNamespace = substr($shortName, 0, $forwardSlashPos);
                preg_match_all(
                    '/^use\s([a-z0-9\\\\]+)' . $partialNamespace . ';$/im',
                    $fileContent,
                    $partialNamespaceMatches
                );
                if ($forwardSlashPos && isset($partialNamespaceMatches[1][0])) {
                    $class = $partialNamespaceMatches[1][0] . $shortName;
                } else {
                    $class = $this->classNameExtractor->getNamespace($fileContent) . '\\' . $shortName;
                }
            }
        }
        return $class;
    }
}
