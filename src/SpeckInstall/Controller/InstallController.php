<?php

namespace SpeckInstall\Controller;

use \Zend\Mvc\Controller\AbstractActionController;
use \Zend\View\Model\ViewModel;

class InstallController extends AbstractActionController
{
    protected $responses;
    protected $mapper;

    public function indexAction()
    {
    }

    public function install()
    {
        $mapper = $this->getMapper();

        $responses = array();
        $responses[] = $this->getEventManager()->trigger('install.create_tables.pre',  $this, array('mapper' => $mapper));
        $responses[] = $this->getEventManager()->trigger('install.create_tables',      $this, array('mapper' => $mapper));
        $responses[] = $this->getEventManager()->trigger('install.create_tables.post', $this, array('mapper' => $mapper));

        return $responses;
    }

    public function schemaAction()
    {
        $params = $this->params()->fromPost();
        $mapper = $this->getMapper();
        $response = $mapper->createDbConfig($params);

        if($response === true) {
            $this->addResponse("Installer stored db config");
        }

        $response = $this->install();
        $this->addResponse($response);

        return new ViewModel(array('responses' => $this->responses));
    }

    public function addResponse($response, $strings=array())
    {
        if (is_string($response)) {
            $this->responses[] = $response;
        } else {
            foreach ($response as $resp) {
                $this->addResponse($resp);
            }
        }
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
