<?php

/**
 * Parent class for nodes in the xliff document
 */
class XliffNode{
	
	//Map tag names to classes
	static protected $mapNameToClass = array(
		'xliff'		=> 'XliffDocument',
		'file'		=> 'XliffFile',
		'body'		=> 'XliffFileBody',
		'header'	=> 'XliffFileHeader',
		'group'		=> 'XliffUnitsGroup',
		'trans-unit'=> 'XliffUnit',
		'source'	=> 'XliffNode',
		'target'	=> 'XliffNode',
	);
	
	
	/**
	 * Holds element's attributes
	 * @var Array 
	 */
	protected $attributes = array();
	
	/**
	 * Holds child nodes that can be repeated inside this node. 
	 * For example, an xliff document can have multiple "file" nodes
	 * @var Array[tag-name][0..n]=XliffNode
	 */
	protected $containers = array();
	
	/**
	 * Indicate which child nodes are supported 
	 * @var Array[tag-name]=>Xliff Class
	 */
	protected $supportedContainers = array();
	
	/**
	 * Holds child nodes that can be presented only once inside this node. 
	 * For example, "trans-unit" element can have only one "source" node
	 * @var Array[tag-name]=XliffNode
	 */
	protected $nodes = array();
	
	/**
	 * Indicate which child nodes are supported 
	 * @var Array[tag-name]=>Xliff Class
	 */
	protected $supportedNodes = array();
	
	/**
	 * Node's text, NULL if none
	 * @var String|NULL
	 */
	protected $textContent=NULL;
	
	/**
	 * Node's tag name
	 * @var string
	 */
	protected $name = '';
	
	function __construct($name=NULL){
		if($name) $this->setName($name);
		//initialize containers array
		foreach($this->supportedContainers as $name=>$class){
			$this->containers[$name] = array();
		}
	}
	
	/**
	 * @return string
	 */
	public function getName()
	{
	    return $this->name;
	}

	/**
	 * @param string $name
	 * @return XliffNode
	 */
	public function setName($name)
	{
	    $this->name = $name;
	    return $this;
	}
	
	
	/**
	 * Returns the attribute value, FALSE if attribute missing
	 * @param string $name
	 * @return Ambigous <boolean, string> - 
	 */
	function getAttribute($name){
		return (isset($this->attributes[$name])) ? $this->attributes[$name] : FALSE;
	}
	/**
	 * Sets an attribute
	 * @param string $name
	 * @param string $value
	 * @throws Exception
	 * @return XliffNode
	 */
	function setAttribute($name, $value){
		/*if (!(string)$value){
			throw new Exception("Attribute must be a string");
		}*/
		$this->attributes[$name] = trim((string)$value);
		return $this;
	}
	
	/**
	 * Set multiple attributes from a key=>value array
	 * @param Array $attr_array
	 * @return XliffNode
	 */
	function setAttributes($attr_array){
		foreach($attr_array as $key=>$val){
			$this->setAttribute($key, $val);
		}
		return $this;
	}
	
	/**
	 * @return Ambigous <string, NULL>
	 */
	public function getTextContent()
	{
	    return $this->textContent;
	}

	/**
	 * @param string $textContent
	 * @return XliffNode
	 */
	public function setTextContent($textContent)
	{
	    $this->textContent = $textContent;
	    return $this;
	}
	
	/**
	 * Append a new node to this element
	 * @param XliffNode $node - node to append
	 * @return XliffNode - this node
	 */
	public function appendNode(XliffNode $node){
		
		//Automatically detect where to append this node
		if (!empty($this->supportedContainers[$node->getName().'s'])){
			$this->containers[$node->getName().'s'][] = $node;
		}elseif(!empty($this->supportedNodes[$node->getName()])){
			$this->nodes[$node->getName()] = $node;
		}else{
			$this->nodes[$node->getName()] = $node;
		}
		return $this;
	}
	
	
	
	/**
	 * Allow calling $node->tag_name($new=FALSE)
	 * Supports the following methods:
	 * 
	 * 1. $node->tag_name(TRUE) - create a new node for "tag_name" and return the new node
	 * 2. $node->tag_name() - fetch the last added node for "tag_name", FALSE if none
	 *
	 * //On the following, notice that tag names are in plural formation...
	 * 3. $node->tag_names() - return an array of tag_name nodes
	 */
	function __call($name, $args){
		$append = (!empty($args) && $args[0]==TRUE);
		$mapNames = array(
			'/^unit/' => 'trans-unit'			
		);
		//re-map short names to actual tag names, for convenience 
		$name = preg_replace(array_keys($mapNames), array_values($mapNames), $name);
		
		//plural ? 
		if (!empty($this->supportedContainers[$name]) ){
			return $this->containers[$name];
		}elseif(!empty($this->supportedContainers[$name.'s'])){
			$pluralName= $name.'s';
			
			//Create new instance if explicitly specified by argument
			if ( $append ){
				
				$cls = $this->supportedContainers[$pluralName];
				
				$this->containers[$pluralName][] = new $cls();
				
			}
			if (empty($this->containers[$pluralName])) return FALSE;
			return end($this->containers[$pluralName]);
			
		}elseif(!empty($this->supportedNodes[$name])){
			
			//Create new node if explicitly required
			if ($append){
				$cls = $this->supportedNodes[$name];
				$this->nodes[$name] = new $cls();
				$this->nodes[$name]->setName($name);
			}
			
			return (!empty($this->nodes[$name])) ? $this->nodes[$name] : FALSE;
		}
		throw new Exception(sprintf("'%s' is not supported for '%s'",$name,get_class($this)));
	}
	
	/**
	 * Export this node to a DOM object
	 * @param DOMDocument $doc - parent DOMDocument must be provided
	 * @return DOMElement
	 */
	function toDOMElement(DOMDocument $doc){
		$element = $doc->createElement($this->getName());
		foreach($this->attributes as $name=>$value){
			$element->setAttribute($name, $value);
		}
		foreach($this->containers as $container){
			foreach($container as $node){
				$element->appendChild($node->toDOMElement($doc));
			}
		}
		foreach($this->nodes as $node){
			$element->appendChild($node->toDOMElement($doc));
		}
		if ($text = $this->getTextContent()){
			$textNode = $doc->createTextNode($text);
			$element->appendChild($textNode);
		}
		return $element;
	}
	
	/**
	 * Convert DOM element to XliffNode structure 
	 * @param DOMNode $element
	 * @throws Exception
	 * @return string|XliffNode
	 */
	public static function fromDOMElement(DOMNode $element){
		if ($element instanceOf DOMText){
			return $element->nodeValue;
		}else{
			$name = $element->tagName;
			
			//check if tag is supported
			if (empty(self::$mapNameToClass[$element->tagName])){
				$cls = 'XliffNode';
				//throw new Exception(sprintf("Tag name '%s' is unsupported",$name));
			}else{
				//Create the XliffNode object (concrete object)
				$cls = self::$mapNameToClass[$element->tagName];
			}
			$node = new $cls($element->tagName);
			/* @var $node XliffNode */
			
			//Import attributes
			foreach ($element->attributes as $attrNode){
				$node->setAttribute($attrNode->nodeName, $attrNode->nodeValue);
			}
			
			//Continue to nested nodes
			foreach($element->childNodes as $child){
				$res = self::fromDOMElement($child);
				if (is_string($res)){
					$node->setTextContent($res);
				}else{
					$node->appendNode($res);
				}
			}
		}
		return $node;

	}



}

/**
 * Wrapper class for Xliff documents.
 * Externally, you'll want to use this class.
 */
class Xliff_Document extends Xliff_Node {
	const XMLNS = 'urn:oasis:names:tc:xliff:document:';
	const XLIFF_VER = '2.0';

	protected $name = 'xliff';
	protected $supported_containers = array( 'file' => 'Xliff_File' );
	protected $version;


	function __construct(){
		parent::__construct();
		$this->version = XLIFF_VER;
	}


	/**
	 * Convert in-memory XLIFF representation to DOMDocument
	 * @return DOMDocument
	 */
	public function to_DOM(){
		$dom = new DOMDocument();
		$dom->formatOutput = true;

		// create the root xliff element w/all children
		$xliff_dom_element = $this->to_DOM_element( $dom );

		// set some attributes on the xliff element
		$xliff_dom_element->setAttribute( 'xmlns', self::XMLNS . $this->version );
		$xliff_dom_element->setAttribute( 'version', $this->version );
		$xliff_dom_element->setAttribute( 'srcLang', $this->srcLang );
		$xliff_dom_element->setAttribute( 'trgLang', $this->trgLang );

		// append the whole enchilada to the DOM
		$dom->appendChild( $xliff_dom_element );

		return $dom;

	}

	/**
	 * Build in-memory XLIFF representation from DOMDocument
	 *
	 * @param DOMDocument $dom
	 * @throws Exception
	 * @return Xliff_Document
	 */
	public static function from_DOM( DOMDocument $dom ) {
		if ( ! isset( $dom->documentElement ) || $dom->documentElement->tagName !== 'xliff' ) {
			throw new Exception( "Not an XLIFF document" );
		}
		return self::fromDOMElement( $dom->documentElement );
	}
}


/**
 * Concrete class for file tag
 */
class Xliff_File extends Xliff_Node {
	protected $tag_name = 'file';
	protected $supported_containers = array(
		'notes'     => 'Xliff_Notes',
		'unit'      => 'Xliff_Unit',
		'group'     => 'Xliff_Group',
	);
	protected $supported_leaf_nodes = array(
		'skeleton'  => 'Xliff_Skeleton',
	);
}

/**
 * Concrete class for skeleton tag
 */
class Xliff_Skeleton extends Xliff_Node {
	protected $tag_name = 'skeleton';
}

/**
 * Concrete class for Notes tag
 */
class Xliff_Notes extends Xliff_Node {
	protected $tag_name = 'body';
	protected $supported_leaf_nodes = array(
		'note'	=> 'Xliff_Note',
	);
}

/**
 * Concrete class for note tag
 */
class Xliff_Note extends Xliff_Node {
	protected $tag_name = 'note';
}

/**
 * Concrete class for group tag
 */
class Xliff_Group extends Xliff_Node {
	protected $tag_name = 'group';
	protected $supported_containers = array(
		'notes'  => 'Xliff_Notes',
		'group'  => 'Xliff_Group',
		'unit'   => 'Xliff_Unit',
	);
}

/**
 * Concrete class for unit tag
 */
class Xliff_Unit extends Xliff_Node {
	protected $tag_name = 'unit';
	protected $supported_containers = array(
		'notes'         => 'Xliff_Notes',
		'originalData'  => 'Xliff_originalData',
		'segment'       => 'Xliff_Segment',
		'ignorable'     => 'Xliff_Ignorable',
	);
}

/**
 * Concrete class for segment tag
 */
class Xliff_Segment extends Xliff_Node {
	protected $tag_name = 'segment';
	protected $supported_leaf_nodes = array(
		'source'   => 'Xliff_Node',
		'target'   => 'Xliff_Node',
	);
}

/**
 * Concrete class for ignorable tag
 */
class Xliff_Ignorable extends Xliff_Node {
	protected $tag_name = 'ignorable';
	protected $supported_leaf_nodes = array(
		'source'   => 'Xliff_Node',
		'target'   => 'Xliff_Node',
	);
}

/**
 * Concrete class for originalData tag
 */
class Xliff_originalData extends Xliff_Node {
	protected $tag_name = 'originalData';
	protected $supported_leaf_nodes = array(
		'data'   => 'Xliff_Data',
	);
}

/**
 * Concrete class for data tag
 */
class Xliff_Data extends Xliff_Node {
	protected $tag_name = 'data';
}