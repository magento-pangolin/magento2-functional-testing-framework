<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\AcceptanceTestFramework\Util;

use Magento\AcceptanceTestFramework\Test\Managers\CestArrayProcessor;

class TestGenerator
{
    /**
     * Test generator.
     *
     * @var TestGenerator
     */
    private static $testGenerator;

    /**
     * TestGenerator constructor.
     */
    private function __construct()
    {
        // private constructor for singleton
    }

    /**
     * Singleton method to retrieve Test Generator
     * @return TestGenerator
     */
    public static function getInstance()
    {
        if (!self::$testGenerator) {
            self::$testGenerator = new TestGenerator();
        }

        return self::$testGenerator;
    }

    /**
     * Load all Cest files as Objects using the Cest Array Processor.
     * @return array
     */
    private function loadAllCestObjects()
    {
        $cestOutput = CestArrayProcessor::getInstance()->getCestData();
        return $cestOutput;
    }

    /**
     * Create a single PHP file containing the $cestPhp using the $filename.
     * If the _generated directory doesn't exist it will be created.
     *
     * @param string $cestPhp
     * @param string $filename
     * @return void
     * @throws \Exception
     */
    private function createCestFile($cestPhp, $filename)
    {
        $exportDirectory = TESTS_MODULE_PATH . "/_generated";
        $exportFilePath = sprintf("%s/%s.php", $exportDirectory, $filename);

        if (!is_dir($exportDirectory)) {
            mkdir($exportDirectory, 0777, true);
        }

        $file = fopen($exportFilePath, 'w');

        if (!$file) {
            throw new \Exception("Could not open the file!");
        }

        fwrite($file, $cestPhp);
        fclose($file);
    }

    /**
     * Assemble ALL PHP strings using the assembleAllCestPhp function. Loop over and pass each array item
     * to the createCestFile function.
     *
     * @return void
     */
    public function createAllCestFiles()
    {
        $cestPhpArray = $this->assembleAllCestPhp();

        foreach ($cestPhpArray as $cestPhpFile) {
            $this->createCestFile($cestPhpFile[1], $cestPhpFile[0]);
        }
    }

    /**
     * Assemble the entire PHP string for a single Test based on a Cest Object.
     * Create all of the PHP strings for a Test. Concatenate the strings together.
     *
     * @param \Magento\AcceptanceTestFramework\Test\Objects\CestObject $cestObject
     * @return string
     */
    private function assembleCestPhp($cestObject)
    {
        $usePhp = $this->generateUseStatementsPhp($cestObject);
        $classAnnotationsPhp = $this->generateClassAnnotationsPhp($cestObject->getAnnotations());
        $className = $cestObject->getName();
        $className = str_replace(' ', '', $className);
        $hookPhp = $this->generateHooksPhp($cestObject->getHooks());
        $testsPhp = $this->generateTestsPhp($cestObject->getTests());

        $cestPhp = "<?php\n";
        $cestPhp .= "namespace Magento\AcceptanceTest\Backend;\n\n";
        $cestPhp .= $usePhp;
        $cestPhp .= $classAnnotationsPhp;
        $cestPhp .= sprintf("class %s\n", $className);
        $cestPhp .= "{\n";
        $cestPhp .= $hookPhp;
        $cestPhp .= $testsPhp;
        $cestPhp .= "}\n";

        return $cestPhp;
    }

    /**
     * Load ALL Cest objects. Loop over and pass each to the assembleCestPhp function.
     *
     * @return array
     */
    private function assembleAllCestPhp()
    {
        $cestObjects = $this->loadAllCestObjects();
        $cestPhpArray = [];

        foreach ($cestObjects as $cest) {
            $name = $cest->getName();
            $name = $string = str_replace(' ', '', $name);
            $php = $this->assembleCestPhp($cest);
            $cestPhpArray[] = [$name, $php];
        }

        return $cestPhpArray;
    }

    /**
     * Creates a PHP string for the necessary Allure and AcceptanceTester use statements.
     * Since we don't support other dependencies at this time, this function takes no parameter.
     *
     * @param \Magento\AcceptanceTestFramework\Test\Objects\CestObject  $cestObject
     * @return string
     */
    private function generateUseStatementsPhp($cestObject)
    {
        $hooks = $cestObject->getHooks();
        $data = false;

        /**
         * Loop over each Hook object, loop over each Step in the Hook object looking for a createData or deleteData.
         * If they are present set a variable to True so the PHP generator uses $this->_____->createData() instead of
         * $______->createData().
         */
        foreach ($hooks as $hook) {
            if (!$data) {
                $steps = $hook->getActions();

                foreach ($steps as $step) {
                    if ($step->getType() === "createData" || $step->getType() === "deleteData") {
                        $data = true;
                        break;
                    }
                }
            }
        }

        $useStatementsPhp = "use Magento\AcceptanceTestFramework\AcceptanceTester;\n";

        if ($data) {
            $useStatementsPhp .= "use Magento\AcceptanceTestFramework\DataGenerator\Managers\DataManager;\n";
            $useStatementsPhp .= "use Magento\AcceptanceTestFramework\DataGenerator\Api\EntityApiHandler;\n";
        }

        $allureStatements = [
            "Yandex\Allure\Adapter\Annotation\Features;",
            "Yandex\Allure\Adapter\Annotation\Stories;",
            "Yandex\Allure\Adapter\Annotation\Title;",
            "Yandex\Allure\Adapter\Annotation\Description;",
            "Yandex\Allure\Adapter\Annotation\Parameter;",
            "Yandex\Allure\Adapter\Annotation\Severity;",
            "Yandex\Allure\Adapter\Model\SeverityLevel;",
            "Yandex\Allure\Adapter\Annotation\TestCaseId;\n"
        ];

        foreach ($allureStatements as $allureUseStatement) {
            $useStatementsPhp .= sprintf("use %s\n", $allureUseStatement);
        }

        return $useStatementsPhp;
    }

    /**
     * Creates a PHP string for the Class Annotations block if the Cest file contains an <annotations> block, outside
     * of the <test> blocks.
     *
     * @param array $classAnnotationsObject
     * @return string
     */
    private function generateClassAnnotationsPhp($classAnnotationsObject)
    {
        $classAnnotationsPhp = "/**\n";

        foreach ($classAnnotationsObject as $annotationType => $annotationName) {
            if ($annotationType == "features") {
                $features = "";

                foreach ($annotationName as $name) {
                    $features .= sprintf("\"%s\"", $name);

                    if (next($annotationName)) {
                        $features .= ", ";
                    }
                }

                $classAnnotationsPhp .= sprintf(" * @Features({%s})\n", $features);
            }

            if ($annotationType == "stories") {
                $stories = "";

                foreach ($annotationName as $name) {
                    $stories .= sprintf("\"%s\"", $name);

                    if (next($annotationName)) {
                        $stories .= ", ";
                    }
                }

                $classAnnotationsPhp .= sprintf(" * @Stories({%s})\n", $stories);
            }

            if ($annotationType == "title") {
                $classAnnotationsPhp .= sprintf(" * @Title(\"%s\")\n", ucwords($annotationType), $annotationName[0]);
            }

            if ($annotationType == "description") {
                $classAnnotationsPhp .= sprintf(" * @Description(\"%s\")\n", $annotationName[0]);
            }

            if ($annotationType == "severity") {
                $classAnnotationsPhp .= sprintf(" * @Severity(level = SeverityLevel::%s)\n", $annotationName[0]);
            }

            if ($annotationType == "testCaseId") {
                $classAnnotationsPhp .= sprintf(" * TestCaseId(\"%s\")", $annotationName[0]);
            }

            if ($annotationType == "group") {
                foreach ($annotationName as $group) {
                    $classAnnotationsPhp .= sprintf(" * @group %s\n", $group);
                }
            }

            if ($annotationType == "env") {
                foreach ($annotationName as $env) {
                    $classAnnotationsPhp .= sprintf(" * @env %s\n", $env);
                }
            }
        }

        $classAnnotationsPhp .= " */\n";

        return $classAnnotationsPhp;
    }

    /**
     * Creates a PHP string for the actions contained withing a <test> block.
     * Since nearly half of all Codeception methods don't share the same signature I had to setup a massive Case
     * statement to handle each unique action. At the bottom of the case statement there is a generic function that can
     * construct the PHP string for nearly half of all Codeception actions.
     * @param array $stepsObject
     * @param array|bool $hookObject
     * @return string
     */
    private function generateStepsPhp($stepsObject, $hookObject = false)
    {
        $testSteps = "";

        foreach ($stepsObject as $steps) {
            $actor = "I";
            $actionName = $steps->getType();
            $customActionAttributes = $steps->getCustomActionAttributes();
            $selector = null;
            $input = null;
            $parameterArray = null;
            $returnVariable = null;
            $x = null;
            $y = null;
            $html = null;
            $url = null;
            $function = null;
            $time = null;

            if (isset($customActionAttributes['returnVariable'])) {
                $returnVariable = $customActionAttributes['returnVariable'];
            }

            if (isset($customActionAttributes['url']) && isset($customActionAttributes['userInput'])) {
                $input = sprintf("\"%s\"", $customActionAttributes['userInput']);
            } elseif (isset($customActionAttributes['userInput'])) {
                $input = sprintf("\"%s\"", $customActionAttributes['userInput']);
            } elseif (isset($customActionAttributes['url'])) {
                $input = sprintf("\"%s\"", $customActionAttributes['url']);
            } elseif (isset($customActionAttributes['time'])) {
                $input = sprintf("\"%s\"", $customActionAttributes['time']);
            }

            if (isset($customActionAttributes['parameterArray'])) {
                $parameterArray = $customActionAttributes['parameterArray'];
            }

            if (isset($customActionAttributes['selectorArray'])) {
                $selector = sprintf("%s", $customActionAttributes['selectorArray']);
            } elseif (isset($customActionAttributes['selector'])) {
                $selector = sprintf("\"%s\"", $customActionAttributes['selector']);
            }

            if (isset($customActionAttributes['x'])) {
                $x = $customActionAttributes['x'];
            }

            if (isset($customActionAttributes['y'])) {
                $y = $customActionAttributes['y'];
            }

            if (isset($customActionAttributes['function'])) {
                $function = $customActionAttributes['function'];
            }

            if (isset($customActionAttributes['html'])) {
                $html = $customActionAttributes['html'];
            }

            if (isset($customActionAttributes['time'])) {
                $time = $customActionAttributes['time'];
            } elseif (isset($customActionAttributes['timeout'])) {
                $time = $customActionAttributes['timeout'];
            }

            switch ($actionName) {
                case "amOnPage":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "amOnSubdomain":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "amOnUrl":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "click":
                    if ($input && $selector) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $selector);
                    } elseif ($input && !$selector) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    } elseif (!$input && $selector) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                    }
                    break;
                case "clickWithLeftButton":
                    if ($selector) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s, %s);\n", $actor, $actionName, $selector, $x, $y);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(null, %s, %s);\n", $actor, $actionName, $x, $y);
                    }
                    break;
                case "clickWithRightButton":
                    if ($selector) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s, %s);\n", $actor, $actionName, $selector, $x, $y);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(null, %s, %s);\n", $actor, $actionName, $x, $y);
                    }
                    break;
                case "createData":
                    $entity = $customActionAttributes['entity'];
                    $key = $steps->getMergeKey();

                    if ($hookObject) {
                        $testSteps .= sprintf(
                            "\t\t$%s = DataManager::getInstance()->getEntity(\"%s\");\n",
                            $entity,
                            $entity
                        );
                        $testSteps .= sprintf("\t\t\$this->%s = new EntityApiHandler($%s);\n", $key, $entity);
                        $testSteps .= sprintf("\t\t\$this->%s->createEntity();\n", $key);
                    } else {
                        $testSteps .= sprintf(
                            "\t\t$%s = DataManager::getInstance()->getEntity(\"%s\");\n",
                            $entity,
                            $entity
                        );
                        $testSteps .= sprintf("\t\t$%s = new EntityApiHandler($%s);\n", $key, $entity);
                        $testSteps .= sprintf("\t\t$%s->createEntity();\n", $key);
                    }
                    break;
                case "deleteData":
                    $key = $customActionAttributes['createDataKey'];

                    if ($hookObject) {
                        $testSteps .= sprintf("\t\t\$this->%s->deleteEntity();\n", $key);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->deleteEntity();\n", $key);
                    }
                    break;
                case "dontSee":
                    if ($selector) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $selector);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "dontSeeCookie":
                    if (isset($parameterArray)) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $parameterArray);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "dontSeeCurrentUrlEquals":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "dontSeeCurrentUrlMatches":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "dontSeeElement":
                    if ($parameterArray) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                    }
                    break;
                case "dontSeeElementInDOM":
                    if ($parameterArray) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                    }
                    break;
                case "dontSeeInCurrentUrl":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "dontSeeInField":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $input);
                    break;
                case "dontSeeInFormFields":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $parameterArray);
                    break;
                case "dontSeeInSource":
                    // TODO: Solve the HTML parsing issue.
                    $testSteps .= sprintf("\t\t$%s->%s(\"%s\");\n", $actor, $actionName, $html);
                    break;
                case "dontSeeLink":
                    if (isset($customActionAttributes['url'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\");\n",
                            $actor,
                            $actionName,
                            $input,
                            $customActionAttributes['url']
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "dragAndDrop":
                    $testSteps .= sprintf(
                        "\t\t$%s->%s(\"%s\", \"%s\");\n",
                        $actor,
                        $actionName,
                        $customActionAttributes['selector1'],
                        $customActionAttributes['selector2']
                    );
                    break;
                case "executeInSelenium":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $function);
                    break;
                case "executeJS":
                    $testSteps .= sprintf("\t\t$%s->%s(\"%s\");\n", $actor, $actionName, $function);
                    break;
                case "fillField":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $input);
                    break;
                case "formatMoney":
                    if (isset($customActionAttributes['locale'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\");\n",
                            $actor,
                            actionName,
                            $input,
                            $customActionAttributes['locale']
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "grabCookie":
                    if (isset($returnVariable)) {
                        if (isset($parameterArray)) {
                            $testSteps .= sprintf(
                                "\t\t$%s = $%s->%s(%s, %s);\n",
                                $returnVariable,
                                $actor,
                                $actionName,
                                $input,
                                $parameterArray
                            );
                        } else {
                            $testSteps .= sprintf(
                                "\t\t$%s = $%s->%s(%s);\n",
                                $returnVariable,
                                $actor,
                                $actionName,
                                $input
                            );
                        }
                    } else {
                        if (isset($parameterArray)) {
                            $testSteps .= sprintf(
                                "\t\t$%s->%s(%s, %s);\n",
                                $actor,
                                $actionName,
                                $input,
                                $parameterArray
                            );
                        } else {
                            $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                        }
                    }
                    break;
                case "grabFromCurrentUrl":
                    if (isset($returnVariable)) {
                        if ($input) {
                            $testSteps .= sprintf(
                                "\t\t$%s = $%s->%s(%s);\n",
                                $returnVariable,
                                $actor,
                                $actionName,
                                $input
                            );
                        } else {
                            $testSteps .= sprintf("\t\t$%s = $%s->%s();\n", $returnVariable, $actor, $actionName);
                        }
                    } else {
                        if ($input) {
                            $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                        } else {
                            $testSteps .= sprintf("\t\t$%s->%s();\n", $actor, $actionName);
                        }
                    }
                    break;
                case "grabValueFrom":
                    if (isset($returnVariable)) {
                        $testSteps .= sprintf(
                            "\t\t$%s = $%s->%s(%s);\n",
                            $returnVariable,
                            $actor,
                            $actionName,
                            $selector
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                    }
                    break;
                case "loginAsAdmin":
                    if (isset($customActionAttributes['username']) && isset($customActionAttributes['password'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(\"%s\", \"%s\");\n",
                            $actor,
                            $actionName,
                            $customActionAttributes['username'],
                            $customActionAttributes['password']
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s();\n", $actor, $actionName);
                    }
                    break;
                case "moveMouseOver":
                    if ($selector) {
                        if (isset($step['x']) || isset($step['y'])) {
                            $testSteps .= sprintf("\t\t$%s->%s(%s, %s, %s);\n", $actor, $actionName, $selector, $x, $y);
                        } else {
                            $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                        }
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(null, %s, %s);\n", $actor, $actionName, $x, $y);
                    }
                    break;
                case "mSetLocale":
                    if (isset($customActionAttributes['locale'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\");\n",
                            $actor,
                            $actionName,
                            $input,
                            $customActionAttributes['locale']
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "performOn":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $function);
                    break;
                case "pressKey":
                    if (isset($parameterArray)) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $input);
                    }
                    break;
                case "resetCookie":
                    if (isset($parameterArray)) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $parameterArray);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "resizeWindow":
                    $testSteps .= sprintf(
                        "\t\t$%s->%s(%s, %s);\n",
                        $actor,
                        $actionName,
                        $customActionAttributes['width'],
                        $customActionAttributes['height']
                    );
                    break;
                case "scrollTo":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s, %s);\n", $actor, $actionName, $selector, $x, $y);
                    break;
                case "searchAndMultiSelectOption":
                    if (isset($customActionAttributes['requiredAction'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $customActionAttributes['parameterArray'],
                            $customActionAttributes['requiredAction']
                        );
                    } else {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $customActionAttributes['parameterArray']
                        );
                    }
                    break;
                case "see":
                    if (isset($customActionAttributes['selector'])) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $selector);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "seeCookie":
                    if (isset($parameterArray)) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $parameterArray);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "seeCurrentUrlEquals":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "seeCurrentUrlMatches":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "seeElement":
                    if (isset($parameterArray)) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                    }
                    break;
                case "seeElementInDOM":
                    if (isset($parameterArray)) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                    }
                    break;
                case "seeInCurrentUrl":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    break;
                case "seeInField":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $input);
                    break;
                case "seeInFormFields":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $parameterArray);
                    break;
                case "seeInPageSource":
                    // TODO: Solve the HTML parsing issue.
                    break;
                case "seeInSource":
                    // TODO: Solve the HTML parsing issue.
                    break;
                case "seeLink":
                    if (isset($step['url'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\");\n",
                            $actor,
                            $actionName,
                            $input,
                            $customActionAttributes['url']
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $input);
                    }
                    break;
                case "seeNumberOfElements":
                    $testSteps .= sprintf(
                        "\t\t$%s->%s(%s, %s);\n",
                        $actor,
                        $actionName,
                        $selector,
                        $customActionAttributes['userInput']
                    );
                    break;
                case "selectOption":
                    if ($parameterArray) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s);\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $input);
                    }
                    break;
                case "setCookie":
                    if ($parameterArray) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\", %s);\n",
                            $actor,
                            $actionName,
                            $input,
                            $customActionAttributes['value'],
                            $parameterArray
                        );
                    } else {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\");\n",
                            $actor,
                            $actionName,
                            $input,
                            $customActionAttributes['value']
                        );
                    }
                    break;
                case "submitForm":
                    if (isset($step['button'])) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\", \"%s\");\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray,
                            $customActionAttributes['button']
                        );
                    } else {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, \"%s\");\n",
                            $actor,
                            $actionName,
                            $selector,
                            $parameterArray
                        );
                    }
                    break;
                case "wait":
                    $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $time);
                    break;
                case "waitForAjaxLoad":
                    if ($input) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $time);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s();\n", $actor, $actionName);
                    }
                    break;
                case "waitForElement":
                    $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $selector, $time);
                    break;
                case "waitForElementChange":
                    $testSteps .= sprintf(
                        "\t\t$%s->%s(%s, %s, %s);\n",
                        $actor,
                        $actionName,
                        $selector,
                        $function,
                        $time
                    );
                    break;
                case "waitForJS":
                    $testSteps .= sprintf("\t\t$%s->%s(\"%s\", %s);\n", $actor, $actionName, $function, $time);
                    break;
                case "waitForPageLoad":
                    if ($time) {
                        $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $time);
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s();\n", $actor, $actionName);
                    }
                    break;
                case "waitForText":
                    if ($selector) {
                        $testSteps .= sprintf(
                            "\t\t$%s->%s(%s, %s, %s);\n",
                            $actor,
                            $actionName,
                            $input,
                            $time,
                            $selector
                        );
                    } else {
                        $testSteps .= sprintf("\t\t$%s->%s(%s, %s);\n", $actor, $actionName, $input, $time);
                    }
                    break;
                default:
                    if ($returnVariable) {
                        if ($selector) {
                            if (isset($customActionAttributes['userInput'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s = $%s->%s(%s, \"%s\");\n",
                                    $returnVariable,
                                    $actor,
                                    $actionName,
                                    $selector,
                                    $customActionAttributes['userInput']
                                );
                            } elseif (isset($customActionAttributes['parameter'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s = $%s->%s(%s, %s);\n",
                                    $returnVariable,
                                    $actor,
                                    $actionName,
                                    $selector,
                                    $customActionAttributes['parameter']
                                );
                            } else {
                                $testSteps .= sprintf(
                                    "\t\t$%s = $%s->%s(%s);\n",
                                    $returnVariable,
                                    $actor,
                                    $actionName,
                                    $selector
                                );
                            }
                        } else {
                            if (isset($customActionAttributes['userInput'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s = $%s->%s(\"%s\");\n",
                                    $returnVariable,
                                    $actor,
                                    $actionName,
                                    $customActionAttributes['userInput']
                                );
                            } elseif (isset($customActionAttributes['parameter'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s = $%s->%s(%s);\n",
                                    $returnVariable,
                                    $actor,
                                    $actionName,
                                    $customActionAttributes['parameter']
                                );
                            } else {
                                $testSteps .= sprintf("\t\t$%s = $%s->%s();\n", $returnVariable, $actor, $actionName);
                            }
                        }
                    } else {
                        if ($selector) {
                            if (isset($customActionAttributes['userInput'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s->%s(%s, \"%s\");\n",
                                    $actor,
                                    $actionName,
                                    $selector,
                                    $customActionAttributes['userInput']
                                );
                            } elseif (isset($customActionAttributes['parameter'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s->%s(%s, %s);\n",
                                    $actor,
                                    $actionName,
                                    $selector,
                                    $customActionAttributes['parameter']
                                );
                            } else {
                                $testSteps .= sprintf("\t\t$%s->%s(%s);\n", $actor, $actionName, $selector);
                            }
                        } else {
                            if (isset($customActionAttributes['userInput'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s->%s(\"%s\");\n",
                                    $actor,
                                    $actionName,
                                    $customActionAttributes['userInput']
                                );
                            } elseif (isset($customActionAttributes['parameter'])) {
                                $testSteps .= sprintf(
                                    "\t\t$%s->%s(%s);\n",
                                    $actor,
                                    $actionName,
                                    $customActionAttributes['parameter']
                                );
                            } else {
                                $testSteps .= sprintf("\t\t$%s->%s();\n", $actor, $actionName);
                            }
                        }
                    }
            }
        }

        return $testSteps;
    }

    /**
     * Creates a PHP string for the _before/_after methods if the Test contains an <before> or <after> block.
     * @param array $hookObjects
     * @return string
     */
    private function generateHooksPhp($hookObjects)
    {
        $hooks = "";
        $createData = false;
        foreach ($hookObjects as $hookObject) {
            $type = $hookObject->getType();
            $dependencies = 'AcceptanceTester $I';

            foreach ($hookObject->getActions() as $step) {
                if ($step->getType() == "createData") {
                    $hooks .= "\t/**\n";
                    $hooks .= sprintf("\t  * @var EntityApiHandler $%s;\n", $step->getMergeKey());
                    $hooks .= "\t */\n";
                    $hooks .= sprintf("\tprotected $%s;\n\n", $step->getMergeKey());
                    $createData = true;
                }
            }

            $steps = $this->generateStepsPhp($hookObject->getActions(), $createData);

            if ($type == "after") {
                $hooks .= sprintf("\tpublic function _after(%s)\n", $dependencies);
                $hooks .= "\t{\n";
                $hooks .= $steps;
                $hooks .= "\t}\n\n";
            }

            if ($type == "before") {
                $hooks .= sprintf("\tpublic function _before(%s)\n", $dependencies);
                $hooks .= "\t{\n";
                $hooks .= $steps;
                $hooks .= "\t}\n\n";
            }

            $hooks .= "";
        }

        return $hooks;
    }

    /**
     * Creates a PHP string for the Test Annotations block if the Test contains an <annotations> block.
     *
     * @param array $testAnnotationsObject
     * @return string
     */
    private function generateTestAnnotationsPhp($testAnnotationsObject)
    {
        $testAnnotationsPhp = "\t/**\n";

        foreach ($testAnnotationsObject as $annotationType => $annotationName) {
            if ($annotationType == "features") {
                $features = "";

                foreach ($annotationName as $name) {
                    $features .= sprintf("\"%s\"", $name);

                    if (next($annotationName)) {
                        $features .= ", ";
                    }
                }

                $testAnnotationsPhp .= sprintf("\t * @Features({%s})\n", $features);
            }

            if ($annotationType == "stories") {
                $stories = "";

                foreach ($annotationName as $name) {
                    $stories .= sprintf("\"%s\"", $name);

                    if (next($annotationName)) {
                        $stories .= ", ";
                    }
                }

                $testAnnotationsPhp .= sprintf("\t * @Stories({%s})\n", $stories);
            }

            if ($annotationType == "title") {
                $testAnnotationsPhp .= sprintf("\t * @Title(\"%s\")\n", $annotationName[0]);
            }

            if ($annotationType == "description") {
                $testAnnotationsPhp .= sprintf("\t * @Description(\"%s\")\n", $annotationName[0]);
            }

            if ($annotationType == "severity") {
                $testAnnotationsPhp .= sprintf("\t * @Severity(level = SeverityLevel::%s)\n", $annotationName[0]);
            }

            if ($annotationType == "testCaseId") {
                $testAnnotationsPhp .= sprintf("\t * @TestCaseId(\"%s\")\n", $annotationName[0]);
            }
        }

        $testAnnotationsPhp .= sprintf("\t * @Parameter(name = \"%s\", value=\"$%s\")\n", "AcceptanceTester", "I");

        foreach ($testAnnotationsObject as $annotationType => $annotationName) {
            if ($annotationType == "group") {
                foreach ($annotationName as $name) {
                    $testAnnotationsPhp .= sprintf("\t * @group %s\n", $name);
                }
            }

            if ($annotationType == "env") {
                foreach ($annotationName as $env) {
                    $testAnnotationsPhp .= sprintf("\t * @env %s\n", $env);
                }
            }
        }

        $testAnnotationsPhp .= sprintf("\t * @param %s $%s\n", "AcceptanceTester", "I");
        $testAnnotationsPhp .= "\t * @return void\n";
        $testAnnotationsPhp .= "\t */\n";

        return $testAnnotationsPhp;
    }

    /**
     * Creates a PHP string based on a <test> block.
     * Concatenates the Test Annotations PHP and Test PHP for a single Test.
     * @param array $testsObject
     * @return string
     */
    private function generateTestsPhp($testsObject)
    {
        $testPhp = "";

        foreach ($testsObject as $test) {
            $testName = $test->getName();
            $testName = str_replace(' ', '', $testName);
            $testAnnotations = $this->generateTestAnnotationsPhp($test->getAnnotations());
            $dependencies = 'AcceptanceTester $I';
            $steps = $this->generateStepsPhp($test->getOrderedActions());

            $testPhp .= $testAnnotations;
            $testPhp .= sprintf("\tpublic function %s(%s)\n", $testName, $dependencies);
            $testPhp .= "\t{\n";
            $testPhp .= $steps;
            $testPhp .= "\t}\n";

            if (sizeof($testsObject) > 1) {
                $testPhp .= "\n";
            }
        }

        return $testPhp;
    }
}