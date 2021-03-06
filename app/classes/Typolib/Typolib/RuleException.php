<?php
namespace Typolib;

use Exception;

/**
 * RuleException class
 *
 * This class provides methods to manage an exception: create, delete or update,
 * check if an exception exists and get all the exceptions for a specific code.
 *
 * @package Typolib
 */
class RuleException
{
    private $id;
    private $content;
    private $rule_id;
    private $file;
    private $exception;
    private $commit_msg;

    /**
     * Constructor that initializes all the arguments then call the method to create
     * the exception if the code and rule exist.
     *
     * @param  String    $code_name   The code name from which the exception depends.
     * @param  String    $code_locale The locale code from which the exception depends.
     * @param  integer   $rule_id     The rule identity from which the exception depends.
     * @param  String    $content     The content of the new exception.
     * @throws Exception if rule exception creation failed.
     */
    public function __construct($code_name, $code_locale, $rule_id, $content)
    {
        $success = false;

        $code = Rule::getArrayRules($code_name, $code_locale, RULES_STAGING);
        if ($code != null && Rule::existRule($code, $rule_id, RULES_STAGING)) {
            $this->content = $content;
            $this->rule_id = $rule_id;
            $this->createException($code_name, $code_locale);

            $success = true;
        }

        if (! $success) {
            throw new Exception('Exception creation failed.');
        }
    }

    /**
     * Creates an exception into the exceptions.php file located inside the code
     * directory.
     *
     * @param String $code_name   The code name from which the exception depends.
     * @param String $code_locale The locale code from which the exception depends.
     */
    private function createException($code_name, $code_locale)
    {
        $this->file = DATA_ROOT . RULES_STAGING . "/$code_locale/$code_name/exceptions.php";
        $this->exception = self::getArrayExceptions($code_name, $code_locale, RULES_STAGING);
        $this->exception['exceptions'][] = ['rule_id' => $this->rule_id,
                                      'content'       => $this->content, ];

        //Get the last inserted id
        end($this->exception['exceptions']);
        $this->id = key($this->exception['exceptions']);

        $this->commit_msg = "Adding exception $this->id in /$code_locale/$code_name";
    }

    public function saveException()
    {
        $repo_mgr = new RepoManager();
        $repo_mgr->checkForUpdates();

        file_put_contents($this->file, serialize($this->exception));

        $repo_mgr->commitAndPush($this->commit_msg);
    }

    /**
     * Allows deleting an exception, or updating the content of an exception.
     *
     * @param  String  $code_name   The code name from which the exception depends.
     * @param  String  $code_locale The locale code from which the exception depends.
     * @param  integer $id          The identity of the exception
     * @param  String  $action      The action to perform: 'delete' or 'update_content'
     * @param  String  $value       The new content of the exception. If action is
     *                              'delete' the value must be empty.
     * @return boolean True if the function succeeds.
     */
    public static function manageException($code_name, $code_locale, $id, $action, $value = '')
    {
        $file = DATA_ROOT . RULES_STAGING . "/$code_locale/$code_name/exceptions.php";

        $exception = self::getArrayExceptions($code_name, $code_locale, RULES_STAGING);
        if ($exception != null && self::existException($exception, $id, RULES_STAGING)) {
            switch ($action) {
                case 'delete':
                    unset($exception['exceptions'][$id]);
                    break;

                case 'update_content':
                    $exception['exceptions'][$id]['content'] = $value;
                    break;

            }

            $repo_mgr = new RepoManager();
            $repo_mgr->checkForUpdates();

            file_put_contents($file, serialize($exception));

            $repo_mgr->commitAndPush("Editing exception $id in /$code_locale/$code_name");

            return true;
        }

        return false;
    }

    /**
     * Check if the exception exists in an exceptions array.
     *
     * @param  array   $exception The array in which the exception must be searched.
     * @param  integer $id        The identity of the exception we search.
     * @return boolean True if the exception exists
     */
    public static function existException($exception, $id)
    {
        return array_key_exists($id, $exception['exceptions']);
    }

    /**
     * Get an array of all the exceptions for a specific code.
     *
     * @param String $code_name   The code name from which the exceptions depend.
     * @param String $code_locale The locale code from which the exceptions depend.
     * @param String $repo        Repository we want to check (staging or production)
     */
    public static function getArrayExceptions($code_name, $code_locale, $repo)
    {
        if (Code::existCode($code_name, $code_locale, $repo)) {
            $file = DATA_ROOT . $repo . "/$code_locale/$code_name/exceptions.php";

            return unserialize(file_get_contents($file));
        }

        return false;
    }

    /**
     * Get the ID of the newly created exception.
     *
     * @return integer $id The identity ID
     */
    public function getId()
    {
        return $this->id;
    }
}
