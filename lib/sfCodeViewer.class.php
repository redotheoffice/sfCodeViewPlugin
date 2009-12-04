<?php

/**
* Class to reflect on your own written code
* 
* It's a simple parser of 'token_get_all()' tokens, it prints a html representation of the code, with links
* 
* Sjoerd de Jong, 2009
* Use for your own purposes, no guarantees
*/
class sfCodeViewer
{
  protected
    $tokens = array(),
    $reflector = null,
    $url = "";
  
  public function __construct($class)
  {
    $this->reflector = new ReflectionClass(is_array($class) ? $class[0] : $class);
    if (is_array($class))
    {
      $this->reflector = $this->reflector->getMethod($class[1]);
    }
    $this->eol = PHP_EOL;
  }
  
  public function setUrl($url)
  {
    $this->url = $url.(substr($url,-1) != '/' ? '/' : '');
  }

  public function getClassReflector()
  {
    if ($this->reflector instanceof ReflectionMethod)
    {
      return $this->reflector->getDeclaringClass();
    }
    return $this->reflector;
  }
  
  private function getWhiteAndComments()
  {
    $output = "";
    while ($t = current($this->tokens))
    {
      if (is_array($t))
      {
        list ($token, $text, $line) = $t;
        if ($token ==  T_WHITESPACE || $token == T_DOC_COMMENT || $token == T_COMMENT)
        {
          $output .= $this->renderToken();
          continue;
        }
      }
      break;
    }
    return $output;
  }
  
  private function renderToken($tag = 'span', $attributes = array())
  {
    $t = current($this->tokens);
    
    if (is_array($t))
    {
      list ($token, $text, $line) = $t;
      $name = token_name($token);
    }
    else
    {
      $token = null;
      $text = $t;  
      $name = 'T_LITERAL';
    }
        
        
    $output = "";
    
    foreach (explode($this->eol, $text) as $index => $line)
    {
      $output .= $index == 0 ? "" : "</li><li>";
      switch ($token)
      {
        case null:
        case T_WHITESPACE:
          $output .= htmlentities($line);
          break;
        default:
          $output .= $this->renderContentTag($tag, htmlentities($line), array_merge(array('class'=>$name),$attributes));
          break;
      }      
    }
    
    next($this->tokens);
    return $output;
  }
  
  private function renderDefinition()
  {
    $definition = array();
    $output = "";
    
    // get the definition type
    $tType = current($this->tokens);
    $output .= $this->renderToken();
    
    list ($typeToken, $text, $line) = $tType;
    $typeName = token_name($typeToken);

    // skip following non-string tokens
    $output .= $this->getWhiteAndComments();
    
    if ($typeToken == T_FUNCTION)
    {
      list ($token, $text, $line) = $this->curTok();
      $output .= "<a name='".$text."'>".$this->renderToken()."</a>";
      $output .= $this->getWhiteAndComments();
      $output .= $this->renderFormalParameter($text);
      $output .= $this->getWhiteAndComments();
      $output .= $this->renderCodeBlock($this->currentParameterScope);
    }
    else
    {
      $output .= $this->renderToken();
    }
    
    return $output;
  }
    
  private $currentParameterScope = null;  
  
  function renderFormalParameter($methodName = "")
  {
    $output = "";
    $this->currentParameterScope = array();
    
    $t = current($this->tokens);
    if ($t === '(')
    {
      $output .= $this->renderToken(); //'('
      $depth = 1;
      $type = ""; //to determine scope
      while (true)
      {
        $t = current($this->tokens);
        $depth += ($t === '(' ? 1 : 0);
        $depth -= ($t === ')' ? 1 : 0);
        if ($depth == 0) break;
        
        //determine scope
        list ($token, $text, $line) = $this->curTok();
        switch ($token)
        {
          case ",":
            $type = "";
            break;
          case T_STRING:
            // a typecast
            $type = $text;
            break;
          case T_VARIABLE:
            // a variable declaration
            if (!empty($type) && class_exists($type))
            {
              $this->currentParameterScope[$text] = new ReflectionClass($type);
            }
            elseif ($this->reflector instanceof ReflectionClass && $this->reflector->hasMethod($methodName))
            {
              //try to figure out from docblock
              $comment = $this->reflector->getMethod($methodName)->getDocComment();
              $matches = array();
              if (preg_match('/\@param\s+(\w+)\s\\'.$text.'/',$comment, $matches))
              {
                if (class_exists($matches[1]))
                {
                  $this->currentParameterScope[$text] = new ReflectionClass($matches[1]);
                }
              }
            }
        }
        
        $output .= $this->renderToken();
      } 
      $output .= $this->renderToken(); //')'
    }
    
    return $output;
  }
  
  private static function getClassNameForMethod($scope, $method)
  {
    while ($scope instanceof ReflectionClass)
    {
      if ($scope->hasMethod($method))
      {
        return $scope->getName();
      }
      $scope = $scope->getParentClass();
    }
    return "";
  }
  
  public function renderStatement(array $scope = array())
  {    
    list ($statementToken, $statementText, $line) = $this->curTok();

    $statementClass = isset($scope[$statementText]) ? $scope[$statementText] : null;

    $output = $this->renderToken();
    
    list ($token, $text, $line) = $this->curTok();

    if ($token === '(')
    {
      //method call
      $className = self::getClassNameForMethod($statementClass,$statementText);
      $statementClass = null;
      if (!empty($className))
      {
        // try to figure out the new scope
        $rm = new ReflectionMethod($className, $statementText);
        $comment = $rm->getDocComment();
        $matches = array();
        if (preg_match('/\@return\s+(\w+)\s/',$rm->getDocComment(), $matches))
        {
          if (class_exists($matches[1]))
          {
            $statementClass = new ReflectionClass($matches[1]);
          }
        }

        // wrap the statement in a link
        $output = $this->renderContentTag('a', $output, array('class'=>'class_link','href'=>$this->url.$className.'#'.$statementText, 'target'=>'_self'));
      }
      $output .= $this->renderFormalParameter();
      list ($token, $text, $line) = $this->curTok();
    }
    switch ($token)
    {
      case T_DOUBLE_COLON:
        if (is_null($statementClass) && $statementToken == T_STRING && class_exists($statementText))
        {
          $statementClass = new ReflectionClass($statementText);
        }
      case T_OBJECT_OPERATOR:
        $output .= $this->renderToken();
        list ($token, $text, $line) = $this->curTok();
        $output .= $this->renderStatement(is_null($statementClass) ? array() : array($text=>$statementClass));
        break;
    }
    
    return $output;
  }
  
  public function curTok()
  {
    static $line = 0;
    $t = current($this->tokens);
    if (is_array($t)){
      $line = $t[2];
      return $t;
    }
    return array($t,$t,$line);
  }
  
  function renderCodeBlock($scope = array())
  {
    if (is_null($scope))
    {
      $scope = array();
    }
    
    //determine scope
    $reflector = ($this->reflector instanceof ReflectionMethod ? $this->reflector->getDeclaringClass() : $this->reflector);
    $scope['self'] = $reflector;
    $scope['$this'] = $reflector;
    $scope['parent'] = $reflector->getParentClass();
    
    $output = "";
    
    $t = current($this->tokens);
    if ($t === '{')
    {
      $thisIndex = rand();
      $output .= "<span class='blockmarker block".$thisIndex."'>".$this->renderToken()."</span>"; //'{'
      while ('}' !== current($this->tokens))
      {
        list ($token, $text, $line) = $this->curTok();
        
        switch ($token)
        {
          case T_NEW:
          case T_INSTANCEOF:
            $output .= $this->renderClassName();
            continue 2;
            
          case T_STRING:
            // if (strtolower($text) !== 'parent' && strtolower($text) !== 'self') break;
          case T_VARIABLE:
            $output .= $this->renderStatement($scope);
            continue 2;
          case '{':
            $output .= $this->renderCodeBlock($scope);
            continue 2;
        }
        $output .= $this->renderToken();
      }
      $output .= "<span class='blockmarker block".$thisIndex."'>".$this->renderToken()."</span>"; //'}'
    }
    
    return $output;
  }
  
  private function renderClassName()
  {
    list ($tType, $text, $line) = current($this->tokens);
    $output = $this->renderToken();
    
    // skip following non-string tokens
    $output .= $this->getWhiteAndComments();
    
    // get the class name
    $tName = current($this->tokens);
    list ($token, $className, $line) = $tName;
    $output .= $this->renderContentTag('a', $this->renderToken(), array('class'=>'class_link', 'href'=>$this->url.$className.($tType == T_NEW ? '#__construct' : ''), 'target'=>'_self'));
    return $output;
  }

  public function render($url)
  {
    $this->setUrl($url);
    $output = "";
    $this->eol = "";
    
    $source = file_get_contents($this->reflector->getFileName());
    
    if ($i = strpos($source, chr(10)))
    {
      // unix or windows style eol
      $this->eol = chr(10);
      if (ord(substr($source,$i+1,1))==13)
      {
        // windows style eol
        $this->eol .= chr(13);
      }
    }
    else
    {
      // mac style
      $this->eol = chr(13);
    }
    
    $lines = explode($this->eol, $source);
    $trimmedLines = array_slice($lines, $this->reflector->getStartLine()-1,$this->reflector->getEndLine() - $this->reflector->getStartLine() + 1);
    $trimmedSource = implode($this->eol,$trimmedLines);

    $this->tokens = token_get_all("<?php\n".$trimmedSource);
    reset($this->tokens);
    next($this->tokens);
    while ($t = current($this->tokens))
    {
      if (is_array($t))
      {
        list ($token, $text, $line) = $t;
        switch ($token) {
          case T_CLASS:
          case T_INTERFACE:
          case T_FUNCTION:
            $output .= $this->renderDefinition();
            continue 2;
          case T_EXTENDS:
            $output .= $this->renderClassName();
            continue 2;
        }
      }
      $output .= $this->renderToken();
    }
    
    $lineNr = $this->reflector->getStartLine()-1;
    $comments = $this->reflector->getDocComment();
    if (!empty($comments))
    {
      $lineNr--;
      $comments = "<li class='doc_comment'>".$comments."</li>";
    }
    
    $link = $this->renderContentTag('a',basename($this->reflector->getFileName()),array('href'=>$this->url.$this->getClassReflector()->getName()));
    
    return "<ol class='code_block'><li class='filename' value='".$lineNr."'>".$link."</li>".$comments."<li>".$output."</li></ol>";
  }  

  public function renderMethods($url)
  {
    $this->setUrl($url);
    $output = "<div class='filename'>Methods: ".basename($this->reflector->getFileName())."</div>";
    $methods = array();
    
    $reflector = $this->reflector instanceof ReflectionMethod ? $this->reflector->getDeclaringClass() : $this->reflector;
    
    foreach ($reflector->getMethods() as $method)
    {
      $name = $method->getName();
      $methods[$name] = array();
      $ref = $method->getDeclaringClass();
      
      while ($ref instanceof ReflectionClass && $ref->hasMethod($name))
      {
        $ref = $ref->getMethod($name)->getDeclaringClass();
        $methods[$name][] = $ref;
        $ref = $ref->getParentClass();
      }
    }
    
    // ksort($methods);
    
    foreach ($methods as $name => $classes)
    {
      $class = $classes[0];
      
      $text = "";
      $inherits = array();

      foreach ($classes as $index => $class)
      {
        $method = $class->getMethod($name);          
        $docComment = $method->getDocComment();

        if ($index == 0)
        {
          $text .= ($method->isFinal() ? ' final' : '');
          $text .= ($method->isAbstract() ? ' abstract' : '');
          $text .= ($method->isPublic() ? ' public' : '');
          $text .= ($method->isPrivate() ? ' private' : '');
          $text .= ($method->isProtected() ? ' protected' : '');
          $text .= ($method->isStatic() ? ' static' : '');          
          
          if ($class->getName() == $reflector->getName())
          {
            $text = $this->renderContentTag('a',$name,array('href'=>'#'.$name, 'target'=>'_self', 'title'=>$docComment)).$text;
          }
          else
          {
            $inherits[] = $this->renderContentTag('a',$class->getName().'::'.$name,array('href'=>$this->url.$class->getName().'#'.$name, 'target'=>'_self', 'title'=>$docComment)).$text;
            $text = "";
          }
        }
        else
        {
          $inherits[] = $this->renderContentTag('a',$class->getName().'::'.$name,array('href'=>$this->url.$class->getName().'#'.$name, 'target'=>'_self', 'title'=>$docComment));
        }        
      }
      
      $t = "";
      while ($inherit = array_pop($inherits))
      {
        $t = $this->renderContentTag('div',$inherit.$t,array('class'=>'inherited'));
      }
      
      $output .= $this->renderContentTag('li',$text.$t);
    }
    
    return $this->renderContentTag('ul',$output,array('class'=>'methods'));
  }

  /**
   * Renders a HTML content tag.
   *
   * @param string $tag         The tag name
   * @param string $content     The content of the tag
   * @param array  $attributes  An array of HTML attributes to be merged with the default HTML attributes
   *
   * @param string An HTML tag string
   */
  public function renderContentTag($tag, $content = null, $attributes = array())
  {
    if (empty($tag))
    {
      return '';
    }

    return sprintf('<%s%s>%s</%s>', $tag, $this->attributesToHtml($attributes), $content, $tag);
  }

  /**
   * Escapes a string.
   *
   * @param  string $value  string to escape
   * @return string escaped string
   */
  static public function escapeOnce($value)
  {
    $value = is_object($value) ? $value->__toString() : (string) $value;

    return self::fixDoubleEscape(htmlentities($value));
  }

  /**
   * Fixes double escaped strings.
   *
   * @param  string $escaped  string to fix
   * @return string single escaped string
   */
  static public function fixDoubleEscape($escaped)
  {
    return preg_replace('/&amp;([a-z]+|(#\d+)|(#x[\da-f]+));/i', '&$1;', $escaped);
  }

  /**
   * Converts an array of attributes to its HTML representation.
   *
   * @param  array  $attributes An array of attributes
   *
   * @return string The HTML representation of the HTML attribute array.
   */
  public function attributesToHtml($attributes)
  {
    return implode('', array_map(array($this, 'attributesToHtmlCallback'), array_keys($attributes), array_values($attributes)));
  }

  /**
   * Prepares an attribute key and value for HTML representation.
   *
   * It removes empty attributes, except for the value one.
   *
   * @param  string $k  The attribute key
   * @param  string $v  The attribute value
   *
   * @return string The HTML representation of the HTML key attribute pair.
   */
  protected function attributesToHtmlCallback($k, $v)
  {
    return false === $v || is_null($v) || ('' === $v && 'value' != $k) ? '' : sprintf(' %s="%s"', $k, $this->escapeOnce($v));
  }
}
