<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\FunctionalTestingFramework\Config\Reader;

/**
 * Filesystem configuration loader. Loads configuration from XML files, split by scopes.
 */
class Filesystem implements \Magento\FunctionalTestingFramework\Config\ReaderInterface
{
    /**
     * File locator
     *
     * @var \Magento\FunctionalTestingFramework\Config\FileResolverInterface
     */
    protected $fileResolver;

    /**
     * Config converter
     *
     * @var \Magento\FunctionalTestingFramework\Config\ConverterInterface
     */
    protected $converter;

    /**
     * The name of file that stores configuration
     *
     * @var string
     */
    protected $fileName;

    /**
     * Path to corresponding XSD file with validation rules for merged config
     *
     * @var string
     */
    protected $schema;

    /**
     * Path to corresponding XSD file with validation rules for separate config files
     *
     * @var string
     */
    protected $perFileSchema;

    /**
     * List of id attributes for merge
     *
     * @var array
     */
    protected $idAttributes = [];

    /**
     * Class of dom configuration document used for merge
     *
     * @var string
     */
    protected $domDocumentClass;

    /**
     * Config validation state object.
     *
     * @var \Magento\FunctionalTestingFramework\Config\ValidationStateInterface
     */
    protected $validationState;

    /**
     * Default scope.
     *
     * @var string
     */
    protected $defaultScope;

    /**
     * File path to schema file.
     *
     * @var string
     */
    protected $schemaFile;

    /**
     * Constructor
     *
     * @param \Magento\FunctionalTestingFramework\Config\FileResolverInterface $fileResolver
     * @param \Magento\FunctionalTestingFramework\Config\ConverterInterface $converter
     * @param \Magento\FunctionalTestingFramework\Config\SchemaLocatorInterface $schemaLocator
     * @param \Magento\FunctionalTestingFramework\Config\ValidationStateInterface $validationState
     * @param string $fileName
     * @param array $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(
        \Magento\FunctionalTestingFramework\Config\FileResolverInterface $fileResolver,
        \Magento\FunctionalTestingFramework\Config\ConverterInterface $converter,
        \Magento\FunctionalTestingFramework\Config\SchemaLocatorInterface $schemaLocator,
        \Magento\FunctionalTestingFramework\Config\ValidationStateInterface $validationState,
        $fileName,
        $idAttributes = [],
        $domDocumentClass = \Magento\FunctionalTestingFramework\Config\Dom::class,
        $defaultScope = 'global'
    ) {
        $this->fileResolver = $fileResolver;
        $this->converter = $converter;
        $this->fileName = $fileName;
        $this->idAttributes = array_replace($this->idAttributes, $idAttributes);
        $this->validationState = $validationState;
        $this->schemaFile = $schemaLocator->getSchema();
        $this->perFileSchema = $schemaLocator->getPerFileSchema() && $validationState->isValidationRequired()
            ? $schemaLocator->getPerFileSchema() : null;
        $this->domDocumentClass = $domDocumentClass;
        $this->defaultScope = $defaultScope;
    }

    /**
     * Load configuration scope
     *
     * @param string|null $scope
     * @return array
     */
    public function read($scope = null)
    {
        $scope = $scope ?: $this->defaultScope;
        $fileList = $this->fileResolver->get($this->fileName, $scope);
        if (!count($fileList)) {
            return [];
        }
        $output = $this->readFiles($fileList);

        return $output;
    }

    /**
     * Read configuration files
     *
     * @param array $fileList
     * @return array
     * @throws \Exception
     */
    protected function readFiles($fileList)
    {
        /** @var \Magento\FunctionalTestingFramework\Config\Dom $configMerger */
        $configMerger = null;
        foreach ($fileList as $key => $content) {
            try {
                if (!$configMerger) {
                    $configMerger = $this->createConfigMerger($this->domDocumentClass, $content);
                } else {
                    $configMerger->merge($content);
                }
            } catch (\Magento\FunctionalTestingFramework\Config\Dom\ValidationException $e) {
                throw new \Exception("Invalid XML in file " . $key . ":\n" . $e->getMessage());
            }
        }
        if ($this->validationState->isValidationRequired()) {
            $errors = [];
            if ($configMerger && !$configMerger->validate($this->schemaFile, $errors)) {
                $message = "Invalid Document \n";
                throw new \Exception($message . implode("\n", $errors));
            }
        }

        $output = [];
        if ($configMerger) {
            $output = $this->converter->convert($configMerger->getDom());
        }
        return $output;
    }

    /**
     * Return newly created instance of a config merger
     *
     * @param string $mergerClass
     * @param string $initialContents
     * @return \Magento\FunctionalTestingFramework\Config\Dom
     * @throws \UnexpectedValueException
     */
    protected function createConfigMerger($mergerClass, $initialContents)
    {
        $result = new $mergerClass(
            $initialContents,
            $this->idAttributes,
            null,
            $this->perFileSchema
        );
        if (!$result instanceof \Magento\FunctionalTestingFramework\Config\Dom) {
            throw new \UnexpectedValueException(
                "Instance of the DOM config merger is expected, got {$mergerClass} instead."
            );
        }
        return $result;
    }
}
