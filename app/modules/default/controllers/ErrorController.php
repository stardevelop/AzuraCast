<?php
use \Entity\Block;

class ErrorController extends \DF\Controller\Action
{
    public function errorAction()
    {
        // Grab the error object from the request
        $errors = $this->_getParam('error_handler');
        
        // 404 error -- controller or action not found
        if (in_array($errors->type, array(
            \Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER,
            \Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION)))
        {
            $uri = $errors->request->getRequestUri();
            $uri = trim($uri, '/');

            $block = Block::getRepository()->findOneBy(array('url' => $uri));
            if ($block instanceof Block)
            {
                $id = $block->id;
                $this->_forward('index', 'page', 'default', array('id' => $block->id));
                return;
            }
            elseif (substr($uri, 0, 3) == 'api')
            {
                // Return a JSON-encoded error for
                $this->doNotRender();

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'status'    => 'error',
                    'error'     => 'API function not found.',
                ), JSON_UNESCAPED_SLASHES);
                return;
            }
            else
            {
                $this->_helper->viewRenderer('error/pagenotfound', null, true);
            
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->message = 'Page not found';
            }
        }
        else if ($errors->exception instanceof \DF\Exception\DisplayOnly)
        {
            $this->_helper->viewRenderer('error/displayonly', NULL, TRUE);
        }
        else if ($errors->exception instanceof \DF\Exception\NotLoggedIn)
        {
            // $this->_helper->viewRenderer('error/notloggedin', NULL, TRUE);
            // $this->view->message = 'Login Required to Access This Page';

            $this->redirectToRoute(array('module' => 'default', 'controller' => 'account', 'action' => 'login'));
            return;
        }
        else if ($errors->exception instanceof \DF\Exception\PermissionDenied)
        {
            $this->_helper->viewRenderer('error/accessdenied', NULL, TRUE);
            $this->view->message = 'Access Denied';
        }
        else
        {
            // Application Error
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->message = 'Application error';

            if (DF_APPLICATION_ENV != "production" || $this->acl->isAllowed('administer all'))
            {
                $this->doNotRender();

                // Pretty error reporting.
                $run = new \Whoops\Run;
                $handler = new \Whoops\Handler\PrettyPageHandler;
                $handler->setPageTitle("Whoops! There was a problem.");

                $run->pushHandler($handler);

                if ($this->isAjax())
                    $run->pushHandler(new \Whoops\Handler\JsonResponseHandler);

                $run->handleException($errors->exception);
            }
        }
        
        $this->view->exception = $errors->exception;
        $this->view->request = $errors->request;
    }
}