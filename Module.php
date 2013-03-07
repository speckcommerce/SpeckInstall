<?

namespace SpeckInstall;

use Zend\Mvc\MvcEvent;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getControllerConfig()
    {
        return array(
            'invokables' => array(
                'speck_install' => '\SpeckInstall\Controller\InstallController',
            ),
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'SpeckInstall\Mapper\Install' => function ($sm) {
                    $mapper = new \SpeckInstall\Mapper\Install;
                    $config = $sm->get('config');
                    if(isset($config['db'])) {
                        $mapper->setDbAdapter($sm->get('Zend\Db\Adapter\Adapter'));
                    }
                    return $mapper;
                },
            ),
        );
    }

    public function getConfig()
    {
        return array(
            'router' => array(
                'routes' => array(
                    'install' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/install',
                            'defaults' => array(
                                'controller' => 'speck_install',
                                'action'     => 'index',
                            ),
                        ),
                        'may_terminate' => true,
                        'child_routes' => array(
                            'schema' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/schema',
                                    'defaults' => array(
                                        'action' => 'schema',
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'view_manager' => array(
                'template_path_stack' => array(
                    __DIR__ . '/view'
                ),
            ),
        );
    }

    public function onBootstrap($e)
    {
        if($e->getRequest() instanceof \Zend\Console\Request){
            return;
        }
        $app = $e->getParam('application');
        $em  = $app->getEventManager();
        $sm  = $em->getSharedManager();

        $em->attach(MvcEvent::EVENT_DISPATCH, array($this , 'install'), 100);

        //install event listener
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckCatalog'        ); });
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckContact'        ); });
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'ZfcUser'             ); });
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckAddress'        ); });
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckCart'           ); });
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckUserAddress'    ); });
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckRandomProducts' ); });
        //$sm->attach('SpeckInstall\Controller\InstallController', 'install.create_tables', function ($e) { return $this->createTables($e, 'SpeckOrder'          ); });

        $sm->attach('SpeckInstall\Controller\InstallController', 'install.setup_multisite', array($this, 'setupMultiSite'));
        $sm->attach('SpeckInstall\Controller\InstallController', 'install.setup_multisite', function ($e) { return $this->createTables($e, 'SpeckMultisite' ); });

        $sm->attach('SpeckInstall\Controller\InstallController', 'install.setup_multisite', function ($e) {
            $name = $e->getParam('website_name');
            $query = "insert into `website`(`website_id`, `name`)VALUES(1, '{$name}')";
            $e->getParam('mapper')->query($query);
            return array('true', 'SpeckInstall inserted site name - "' . $name . '"');
        });
    }

    public function createTables($e, $moduleName)
    {
        $mm = $e->getTarget()->getServiceLocator()->get('modulemanager');

        $module = $mm->getModule($moduleName);
        if(null === $module) {
            return array(false,  'Missing module - ' . $moduleName . ' - Did you run composer install?');
        }
        $reflection = new \ReflectionClass($module);
        $path = dirname($reflection->getFileName());

        try {
            $create = file_get_contents($path .'/data/schema.sql');
            if(!$create) { throw new \Exception('cannot find schema file'); }
            $mapper = $e->getParam('mapper');
            $mapper->query($create);
        } catch (\Exception $e) {
            return array(false, "SpeckInstaller was unable to complete 'createTables' for {$moduleName} - " . $e->getMessage());
        }
        return array(true, "SpeckInstaller ran 'createTables' for {$moduleName}");
    }

    public function setupMultisite($e)
    {
        $multisite = $e->getParam('multi_site');

        $mm = $e->getTarget()->getServiceLocator()->get('modulemanager');

        $module = $mm->getModule('SpeckMultisite');
        if(null === $module) {
            return array(false,  'setup multisite - Missing module - SpeckMultisite - Did you run composer install?');
        }
        $reflection = new \ReflectionClass($module);
        $path = dirname($reflection->getFileName());
        try {
            $config = include($path .'/config/module.SpeckMultisite.dist.php');
            $content = "<?php\nreturn " . var_export($config, 1) . ';';
            file_put_contents('config/autoload/multisite.global.php', $content);
        } catch (\Exception $e) {
            return array(false, "SpeckInstaller was unable to complete 'setupMultisite' for {$moduleName} - " . $e->getMessage());
        }

        return array(true, "stored SpeckMultisite config");
    }


    public function createZfcUserTables($e)
    {
        $this->createTables('ZfcUser');
    }

    public function install($e)
    {
        if($e->getRouteMatch()->getParam('controller') === 'speck_install') {
            return;
        }
        $response = $e->getResponse();
        $response->setStatusCode(307)->getHeaders()->addHeaderLine('Location', '/install');
    }
}
