<?php

namespace SpeckInstall\Controller;

use \Zend\Mvc\Controller\AbstractActionController;
use \Zend\View\Model\ViewModel;

class InstallController extends AbstractActionController
{
    protected $responses;
    protected $mapper;
    protected $okToContinue = true;

    public function indexAction()
    {
    }

    public function schemaAction()
    {
        $params = $this->params()->fromPost();
        $mapper = $this->getMapper();

        try { $response = $mapper->createDbConfig($params); } catch (\Exception $e) { }
        if (isset($response) && $response === true) {
            $this->addResponse(array(true, 'Store DB Config'));
        } else {
            $this->addResponse(array(false, 'Store DB Config - ' . $e->getMessage()));
            return $this->finish(false);
        }
        $response = $this->getEventManager()->trigger('install.create_tables.pre',  $this, array('mapper' => $mapper));
        if (!$this->addResponse($response)) { return $this->finish(false); }

        $response = $this->getEventManager()->trigger('install.create_tables',      $this, array('mapper' => $mapper));
        if (!$this->addResponse($response)) { return $this->finish(false); }

        $response = $this->getEventManager()->trigger('install.create_tables.post', $this, array('mapper' => $mapper));
        if (!$this->addResponse($response)) { return $this->finish(false); }

        $response = $this->getEventManager()->trigger('install.setup_multisite',    $this, array('mapper' => $mapper, 'site_name' => $params['site_name']));
        if (!$this->addResponse($response)) { return $this->finish(false); }

        return $this->finish(true);
    }

    public function addResponse($response, $strings=array())
    {
        if($this->okToContinue === false) {
            return false;
        }
        if (is_string($response)) {
            $this->responses[] = array(true, $response);
        } elseif (count($response) == 2 && is_bool($response[0])) {
            $this->responses[] = $response;
            if($response[0] === false) {
                $this->okToContinue = false;
                return false;
            }
        } else {
            foreach ($response as $resp) {
                $this->addResponse($resp);
            }
        }
        return true;
    }

    public function finish($success)
    {
        $view = new ViewModel(array('responses' => $this->responses, 'success' => $success));
        return $view->setTemplate('/speck-install/install/finish');
    }

    /**
     * @return mapper
     */
    public function getMapper()
    {
        if (null === $this->mapper) {
            $this->mapper = $this->getServiceLocator()->get('SpeckInstall\Mapper\Install');
        }
        return $this->mapper;
    }

    /**
     * @param $mapper
     * @return self
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

}
