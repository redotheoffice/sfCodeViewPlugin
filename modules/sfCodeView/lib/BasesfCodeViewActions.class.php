<?php

/**
 * Base actions for the sfCodeViewPlugin sfCodeView module.
 * 
 * @package     sfCodeViewPlugin
 * @subpackage  sfCodeView
 * @author      Your name here
 * @version     SVN: $Id: BaseActions.class.php 12628 2008-11-04 14:43:36Z Kris.Wallsmith $
 */
abstract class BasesfCodeViewActions extends sfActions
{
 /**
  * Executes index action
  *
  * @param sfRequest $request A request object
  */
  public function executeIndex(sfWebRequest $request)
  {
    $this->class = $request->getParameter('class');
    if (empty($this->class))
    {
      $this->class = $request->getGetParameter('class');
    }
    
    $this->method = $request->getParameter('method');
    if (empty($this->method))
    {
      $this->method = $request->getGetParameter('method');
    }

    if (!empty($this->method))
    {
      $this->class = array($this->class, $this->method);
    }

    $this->viewer = new sfCodeViewer($this->class);    
    
    if ($request->isXmlHttpRequest())
    {
      sfProjectConfiguration::getActive()->loadHelpers('Url');
      $this->renderText($this->viewer->render(url_for('sfCodeView')));
      return sfView::NONE;
    }
    else
    {
      // maintain history
      $this->history = $this->getUser()->getAttribute('history', array());
      if (false !== ($index = array_search($this->class, $this->history)))
      {
        unset($this->history[$index]);
      }
      array_unshift($this->history, $this->class);
      array_splice($this->history, 10);
      $this->getUser()->setAttribute('history', $this->history);
    }
  }
}
