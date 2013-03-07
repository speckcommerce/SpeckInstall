<?php

namespace SpeckInstall\Mapper;

use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorAwareTrait;
use \Zend\Db\Adapter\Adapter;

class Install implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    protected $dbAdapter;

    public function initDbAdapter($params)
    {
        if ($this->dbAdapter) {
            return;
        }

    }

    public function dbConfig($params)
    {
        $dbDist = 'config/autoload/database.local.php.dist';
        $config = include($dbDist);

        $config['db']['username'] = $params['user'];
        $config['db']['password'] = $params['pass'];
        $config['db']['dsn'] = "mysql:dbname={$params['db_name']};host={$params['host']}";

        return $config;
    }

    public function testDbConfig($dbConfig, $returnAdapter = false)
    {
        $adapter = new Adapter($dbConfig);
        try {
            $adapter->getCurrentSchema();
        } catch (\Zend\Db\Adapter\Exception\RuntimeException $e) {
            $message = $e->getMessage();
        }
        if (isset($message)) {
            return $message;
        } else if ($returnAdapter) {
            return $adapter;
        } else {
            return true;
        }
    }

    public function createDbConfig($params)
    {
        $db = 'config/autoload/database.local.php';

        $config = $this->dbConfig($params);
        $test = $this->testDbConfig($config['db']);

        if ($test !== true) {
            throw new \Exception($test);
        }

        $content = "<?php\nreturn " . var_export($config, 1) . ';';

        file_put_contents($db, $content);

        return true;
    }

    /**
     * @return dbAdapter
     */
    public function getDbAdapter()
    {
        return $this->dbAdapter;
    }

    /**
     * @param $dbAdapter
     * @return self
     */
    public function setDbAdapter($dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
        return $this;
    }
}
