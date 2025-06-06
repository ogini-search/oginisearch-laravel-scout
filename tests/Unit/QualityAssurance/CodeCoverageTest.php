<?php

namespace OginiScoutDriver\Tests\Unit\QualityAssurance;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * @group quality-assurance
 */
class CodeCoverageTest extends TestCase
{
    /**
     * Test that all public methods have corresponding tests.
     * @group quality-assurance
     */
    public function testAllPublicMethodsHaveTests(): void
    {
        $srcPath = __DIR__ . '/../../../src';
        $testPath = __DIR__ . '/../../';

        $phpFiles = $this->getPhpFiles($srcPath);
        $missingTests = [];

        foreach ($phpFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (!$className) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
                $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

                foreach ($publicMethods as $method) {
                    if ($method->getDeclaringClass()->getName() !== $className) {
                        continue; // Skip inherited methods
                    }

                    if (strpos($method->getName(), '__') === 0) {
                        continue; // Skip magic methods
                    }

                    $testExists = $this->hasCorrespondingTest($className, $method->getName(), $testPath);
                    if (!$testExists) {
                        $missingTests[] = "{$className}::{$method->getName()}()";
                    }
                }
            } catch (\Exception $e) {
                // Skip classes that can't be reflected
                continue;
            }
        }

        if (!empty($missingTests)) {
            $this->fail(
                "The following public methods are missing tests:\n" .
                    implode("\n", $missingTests)
            );
        }

        $this->assertTrue(true, 'All public methods have corresponding tests');
    }

    /**
     * Test that critical classes have comprehensive test coverage.
     * @group quality-assurance
     */
    public function testCriticalClassesCoverage(): void
    {
        $criticalClasses = [
            'OginiScoutDriver\\Engine\\OginiEngine',
            'OginiScoutDriver\\Client\\OginiClient',
            'OginiScoutDriver\\Exceptions\\OginiException',
            'OginiScoutDriver\\Logging\\OginiLogger',
            'OginiScoutDriver\\Monitoring\\PerformanceMonitor',
            'OginiScoutDriver\\Monitoring\\HealthChecker',
            'OginiScoutDriver\\Monitoring\\StatusReporter',
        ];

        $testPath = __DIR__ . '/../../';
        $missingCriticalTests = [];

        foreach ($criticalClasses as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $testFile = $this->findTestFileForClass($className, $testPath);
            if (!$testFile) {
                $missingCriticalTests[] = $className;
            }
        }

        $this->assertEmpty(
            $missingCriticalTests,
            'Critical classes missing test files: ' . implode(', ', $missingCriticalTests)
        );
    }

    /**
     * Test that all exception classes have proper test coverage.
     * @group quality-assurance
     */
    public function testExceptionClassesCoverage(): void
    {
        $exceptionPath = __DIR__ . '/../../../src/Exceptions';
        $exceptionFiles = $this->getPhpFiles($exceptionPath);
        $missingExceptionTests = [];

        foreach ($exceptionFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (!$className || !class_exists($className)) {
                continue;
            }

            $testFile = $this->findTestFileForClass($className, __DIR__ . '/../../');
            if (!$testFile) {
                $missingExceptionTests[] = $className;
            }
        }

        $this->assertEmpty(
            $missingExceptionTests,
            'Exception classes missing test files: ' . implode(', ', $missingExceptionTests)
        );
    }

    /**
     * Get all PHP files in a directory recursively.
     */
    private function getPhpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $phpFiles = new RegexIterator($iterator, '/\.php$/');
        $files = [];

        foreach ($phpFiles as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * Extract class name from PHP file.
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            $namespace = $namespaceMatches[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            $className = $classMatches[1];
            return $namespace . '\\' . $className;
        }

        return null;
    }

    /**
     * Check if a method has a corresponding test.
     */
    private function hasCorrespondingTest(string $className, string $methodName, string $testPath): bool
    {
        $testFile = $this->findTestFileForClass($className, $testPath);
        if (!$testFile) {
            return false;
        }

        $testContent = file_get_contents($testFile);

        // Look for test methods that might test this method
        $testMethodPatterns = [
            "/function\s+test.*{$methodName}/i",
            "/function\s+test{$methodName}/i",
            "/@test.*{$methodName}/i",
        ];

        foreach ($testMethodPatterns as $pattern) {
            if (preg_match($pattern, $testContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find test file for a given class.
     */
    private function findTestFileForClass(string $className, string $testPath): ?string
    {
        $classBaseName = basename(str_replace('\\', '/', $className));
        $possibleTestFiles = [
            $testPath . 'Unit/' . $classBaseName . 'Test.php',
            $testPath . 'Integration/' . $classBaseName . 'Test.php',
            $testPath . 'Feature/' . $classBaseName . 'Test.php',
        ];

        // Also search recursively
        $allTestFiles = $this->getPhpFiles($testPath);
        foreach ($allTestFiles as $testFile) {
            if (strpos($testFile, $classBaseName . 'Test.php') !== false) {
                return $testFile;
            }
        }

        foreach ($possibleTestFiles as $testFile) {
            if (file_exists($testFile)) {
                return $testFile;
            }
        }

        return null;
    }
}
