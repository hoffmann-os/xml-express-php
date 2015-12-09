<?php

namespace ML_Express;

/**
 * Simplifies respectively unifies the creation of XML documents.
 *
 */
class Xml
{
	const MIME_TYPE = 'application/xml';
	const FILENAME_EXTENSION = 'xml';

	/** Most commonly '1.0' */
	const XML_VERSION = self::XML_VERSION_1_0;

	const CHARACTER_ENCODING = self::UTF8;

	/** Whether the document should begin with a xml declaration or not. */
	const XML_DECLARATION = true;

	const DOCTYPE = null;
	const SGML_MODE = false;
	const ROOT_ELEMENT = null;
	const XML_NAMESPACE = null;
	const DEFAULT_LINE_BREAK = self::LF;
	const DEFAULT_INDENTATION = self::HT;
	const DEFAULT_LTRIM = true;

	/** This option defines the characters used for line breaks. */
	const OPTION_LINE_BREAK = 'lineBreak';

	/** This option defines the characters used for indentation. */
	const OPTION_INDENTATION = 'indentation';

	/** This option defines whether leading space should be removed or not. */
	const OPTION_LTRIM = 'ltrim';

	const XML_VERSION_1_0 = '1.0';
	const XML_VERSION_1_1 = '1.1';
	const UTF8 = 'UTF-8';
	const LF = "\n";
	const CR = "\r";
	const HT = "\t";

	const CDATA_START = '<![CDATA[';
	const CDATA_STOP = ']]>';

	protected $name;
	protected $content;
	protected $cdata;
	protected $attributes;
	protected $options;
	protected $children;
	protected $parent;
	protected $root;
	protected $ancestor;
	protected $sub = false;

	/**
	 * The constructor is used internally to create child elements.
	 *
	 * You must not overwrite the constructor.
	 * For creating the root element, write a static factory method instead:
	 *
	 * <pre><code>
	 * public static function createHtml($lang = null, $manifest = null)
	 * {
	 *     return (new Html('html'))
	 *             ->setLang($lang)
	 *             ->setManifest($manifest);
	 * }
	 * </code></pre>
	 *
	 * @param string		$name			Name of the new element.
	 * @param string		$content		Content of the new element.
	 * @param Xml			$root			The root element.
	 * @param Xml			$ancestor		Nearest ancestor.
	 */
	public final function __construct(
			$name = null,
			$content = null,
			Xml $root = null,
			Xml $ancestor = null,
			Xml $parent = null)
	{
		if (isset($root)) {
			$this->setRoot($root);
			$this->setAncestor($ancestor);
		}
		else {
			$this->setRoot($this);
			$this->setAncestor($this);
			if (isset($name)) {
				$this->getAncestor()->options = [
						self::OPTION_LINE_BREAK => static::DEFAULT_LINE_BREAK,
						self::OPTION_INDENTATION => static::DEFAULT_INDENTATION,
						self::OPTION_LTRIM => static::DEFAULT_LTRIM
				];
			}
		}
		$this->name = $name;
		$this->content = $content;
		$this->cdata = false;
		$class = get_class($this->getRoot());
		$this->attributes = new XmlAttributes($this);
		$this->children = array();
		$this->parent = $parent;
	}

	/**
	 * Creates a subtree.
	 *
	 * Use this function, if you don't want to start your tree with the root element:<br>
	 * <code>$article = Html5::createSub()->article()->setId($id);</code>
	 *
	 * @param	string	$name		Name of the element.
	 * @param	string	$content	Content of the element.
	 * @return Xml
	 */
	public static final function createSub($name = '', $content = null)
	{
		$class = get_called_class();
		$element = new $class($name, $content);
		$element->sub = true;
		return $element;
	}

	/**
	 * Returns the root element.
	 *
	 * @return Xml
	 */
	public final function getRoot()
	{
		$root = $this->root;
		if ($root->isRoot())
			return $root;
		return $root->getRoot();
	}

	/**
	 * Returns a child element.
	 *
	 */
	public final function getChild($index = 0)
	{
		return $this->children[$index];
	}

	public final function getParent($n = null)
	{
		if ($n > 1)
			return $this->parent->getParent(--$n);
		return $this->parent;
	}

	/**
	 * Removes a child element.
	 *
	 * @param int $index
	 */
	public function removeChild($index)
	{
		unset($this->children[$index]);
		return $this;
	}

	/**
	 * Set options for the current element and its child elements.
	 *
	 * Options currently provided are:
	 * <ul>
	 * <li>OPTION_LINE_BREAK
	 * <li>OPTION_INDENTATION
	 * </ul>
	 *
	 * @param	array	$options	Assotiative array.
	 * 								Use one of the constants listed above as key.
	 * @return	Xml
	 */
	public final function setOptions($options)
	{
		if (!isset($options[self::OPTION_LINE_BREAK])
				&& !isset($options[self::OPTION_INDENTATION])
				&& !isset($options[self::OPTION_LTRIM]))
			return $this;
		if (!$this->isAncestor()) {
			$prevAncestor = $this->getAncestor();
			$this->setAncestor($this);
			$this->options = [
					self::OPTION_LINE_BREAK => $prevAncestor->options[self::OPTION_LINE_BREAK],
					self::OPTION_INDENTATION => $prevAncestor->options[self::OPTION_INDENTATION],
					self::OPTION_LTRIM => $prevAncestor->options[self::OPTION_LTRIM]
			];
		}
		$this->setAncestorOption($options, self::OPTION_LINE_BREAK);
		$this->setAncestorOption($options, self::OPTION_INDENTATION);
		$this->setAncestorOption($options, self::OPTION_LTRIM);
		return $this;
	}

	/**
	 * @see		self::setOptions()
	 * @param	string	$key
	 * @param	string	$value
	 * @return	Xml
	 */
	public final function setOption($key, $value)
	{
		return $this->setOptions([$key => $value]);
	}

	/**
	 * Child elements appears in same line.
	 *
	 * A shortcut for <code>setOption(self::OPTION_LINE_BREAK, '')</code>
	 *
	 * @return	Xml
	 */
	public final function inLine()
	{
		return $this->setOption(self::OPTION_LINE_BREAK, '');
	}

	/**
	 * Subordinated content, elements will be inserted into a CDATA section.
	 *
	 * @param	boolean		$cdata
	 * @return	Xml
	 */
	public final function cdata($cdata = true)
	{
		$this->cdata = $cdata;
		return $this;
	}

	/**
	 * Adds a child element.
	 *
	 * @param	string 			$name		Name of the new element.
	 * @param	string|null		$content	Content of the new element.
	 * @return	Xml
	 */
	public final function append($name, $content = null)
	{
		return $this->appendChild($this->newChild($name, $content));
	}

	/**
	 * Appends a previously created subtree.
	 *
	 * @param	Xml		$element	Root element of the subtree.
	 * @return	Xml
	 */
	public final function inject(Xml $element)
	{
		$element->setAncestor($this->getAncestor());
		$element->setRoot($this->getRoot());
		return $this->appendChild($element);
	}

	/**
	 * Appends a text line.
	 *
	 * @param	string	$text
	 * @return	Xml
	 */
	public final function appendText($text = '')
	{
		$this->appendChild((string) $text);
		return $this;
	}

	/**
	 * Appends a comment.
	 *
	 * @param	string	$content	The comment.
	 * @return	Xml
	 */
	public function comment($content)
	{
		return $this->appendText('<!-- ' . $content . ' -->');
	}

	/**
	 * Appends a new or sets an already existing attribute.
	 *
	 * @param     string         $name           Name of the attribute.
	 * @param     mixed          $value          Value of the attribute.
	 * @return    Xml
	 */
	public final function attrib($name, $value)
	{
		$this->attributes->setAttrib($name, $value);
		return $this;
	}

	public function complexAttrib($name, $value, $delimiter = ' ', $check = false)
	{
		$this->attributes->setComplexAttrib($name, $value, $delimiter, $check);
		return $this;
	}

	public function booleanAttrib($name, $value = true, $comparisonAttribute = null)
	{
		$this->attributes->setBooleanAttrib($name, $value, $comparisonAttribute);
		return $this;
	}

	/**
	 * Generates the desired markup.
	 *
	 * @param	string	$indentation	Initial indentation.
	 * @return	string
	 */
	public final function getMarkup($indentation = '')
	{
		$line = $this->getOption(self::OPTION_LINE_BREAK);
		$tab = $this->getOption(self::OPTION_INDENTATION);

		$xmlString = '';
		if (empty($this->name)) {
			$last = count($this->children) - 1;
			foreach ($this->children as $i => $child) {
				$xmlString .= $child->getMarkup($indentation);
				if ($i < $last) {
					$xmlString .= $line;
				}
			}
			return $xmlString;
		}

		$class = get_class($this->getRoot());
		$xmlDeclaration = $class::XML_DECLARATION;
		$doctype = $class::DOCTYPE;
		$sgmlMode = $class::SGML_MODE;

		// no linebreak -> no tabspace
		$indent1 = $indentation;
		if ($line == '') {
			$indent2 = '';
			$indent3 = '';
		}
		else {
			$indent2 = $indentation . $tab;
			$indent3 = $indentation;
		}

		if ($this->isRoot() && !$this->sub) {
			if ($xmlDeclaration && !$sgmlMode) {
				$xmlString .= $this->xmlDecl();
			}
			if ($doctype != null) {
				$xmlString .= $doctype . $line;
			}
		}
		if (count($this->children) > 0) {
			$xmlString .= $indent1 . $this->openingTag() . $line;
			if ($this->cdata) {
				$xmlString .= $indent3 . self::CDATA_START . $line;
			}
			if ($this->content != null) {
				$xmlString .= $indent2 . $this->prepareContent(
						$this->content, $indent2, $line) . $line;
			}
			foreach ($this->children as $child) {
				if (is_string($child)) {
					$xmlString .= $indent2 . $this->prepareContent(
							$child, $indent2, $line) . $line;
				}
				else {
					$xmlString .= $child->getMarkup($indent2) . $line;
				}
			}
			if ($this->cdata) {
				$xmlString .= $indent3 . self::CDATA_STOP . $line;
			}
			$xmlString .= $indent3 . $this->closingTag();
		}
		else {
			$xmlString .= $indent1 . $this->element($sgmlMode, $indent1, $line, $this->cdata);
		}
		return $xmlString;
	}

	/**
	 * Sets the language attribute.
	 *
	 * @param	string	$lang	A BCP 47 language tag. For example 'en' or 'fr-CA'
	 * @return	Xml
	 */
	public function setLang($lang)
	{
		return $this->attrib('xml:lang', $lang);
	}

	const SPACE_DEFAULT = 'default';
	const SPACE_PRESERVE = 'preserve';

	public function setSpace($space = self::SPACE_PRESERVE) {
		return $this->attrib('xml:space', $space);
	}

	public function setBase($base)
	{
		return $this->attrib('xml:base', $base);
	}

	public function setId($id)
	{
		return $this->attrib('xml:id', $id);
	}

	public function setXmlns($uri, $prefix = null)
	{
		return $this->attrib(empty($prefix) ? 'xmlns' : 'xmlns:' . $prefix, $uri);
	}

	public function p_($n = null)
	{
		return $this->getParent($n);
	}

	public function r_()
	{
		return $this->getRoot();
	}

	public function l_()
	{
		return $this->inLine();
	}

	public function t_($text = '')
	{
		return $this->appendText($text);
	}

	public function m_($indentation = '')
	{
		return $this->getMarkup($indentation);
	}

	public static function c_($name = '', $content = null)
	{
		return static::createSub($name, $content);
	}

	public function rl_()
	{
		return $this->r_()->l_();
	}

	public function pt_($text)
	{
		return $this->p_()->t_($text);
	}

	public function plt_($text)
	{
		return $this->p_()->l_()->t_($text);
	}

	public function __toString()
	{
		return $this->getMarkup();
	}

	/**
	 * Sets the header fields "Content-Type" and "Content-Disposition".
	 *
	 * Considers MIME_TYPE, CHARACTER_ENCODING and FILENAME_EXTENSION.
	 *
	 * @param	string		$filename		Eventually without extension.
	 * @param	boolean		$addExtension	Whether the filename extension
	 * 										should be appended or not.
	 */
	public static function headerfields($filename = null, $addExtension = true)
	{
		if (!empty($filename)) {
			header(self::getContentDispositionHeaderfield($filename, $addExtension));
		}
		header(self::getContentTypeHeaderfield());
	}

	/**
	 * Creates string for "Content-Disposition" header field.
	 *
	 * Considers FILENAME_EXTENSION.
	 *
	 * @param	string		$filename		Eventually without extension.
	 * @param	boolean		$addExtension	Whether the filename extension
	 * 										should be appended or not.
	 * @return	string
	 */
	public static function getContentDispositionHeaderfield($filename, $addExtension = true)
	{
		if ($addExtension) {
			$filename .= '.' . static::FILENAME_EXTENSION;
		}
		return sprintf("Content-Disposition: attachment; filename=\"%s\"", $filename);
	}

	/**
	 * Creates string for "Content-type" header field.
	 *
	 * Considers MIME_TYPE and CHARACTER_ENCODING.
	 *
	 * @return	string
	 */
	public static function getContentTypeHeaderfield()
	{
		return sprintf('Content-Type: %s; charset=%s',
				static::MIME_TYPE, static::CHARACTER_ENCODING);
	}

	protected final function getOptions()
	{
		return $this->ancestor->options;
	}

	/**
	 *
	 * @param	string	$name
	 */
	protected final function getOption($name)
	{
		return $this->ancestor->options[$name];
	}

	/**
	 *
	 */
	protected final function getAncestor()
	{
		return $this->ancestor;
	}

	/**
	 *
	 * @return boolean
	 */
	protected final function isRoot()
	{
		return $this->root === $this;
	}

	/**
	 * Whether options set for this element or not.
	 *
	 * @return boolean
	 */
	protected final function isAncestor()
	{
		return $this->ancestor === $this;
	}

	/**
	 *
	 * @param	Xml		$root
	 */
	protected final function setRoot(Xml $root)
	{
		$this->root = $root;
	}

	/**
	 *
	 * @param	Xml		$ancestor
	 */
	protected final function setAncestor(Xml $ancestor)
	{
		$this->ancestor = $ancestor;
	}

	/**
	 *
	 * @param Xml|string $element
	 * @return string|Xml
	 */
	protected final function appendChild($element)
	{
		$this->children[] = $element;
		return $element;
	}

	protected final function newChild($name, $content = null)
	{
		$class = get_class($this);
		return new $class($name, $content, $this->getRoot(), $this->getAncestor(), $this);
	}

	private final function setAncestorOption($options, $key)
	{
		if (isset($options[$key])) {
			$this->getAncestor()->options[$key] = $options[$key];
		}
	}

	/* element without Children */
	private final function element($sgmlMode, $indent, $line, $cdata)
	{
		if ($this->content === null || (empty($this->content) && !$sgmlMode)) {
			return $this->standaloneTag($sgmlMode);
		}
		else {
			$content = $this->prepareContent($this->content, $indent, $line);
			if ($cdata) {
				$content = self::CDATA_START . $content . self::CDATA_STOP;
			}
			return $this->openingTag() . $content . $this->closingTag();
		}
	}

	private final function openingTag()
	{
		return '<' . $this->name . $this->attributes->str() . '>';
	}

	private final function closingTag()
	{
		return '</' . $this->name . '>';
	}

	private final function standaloneTag($sgmlMode)
	{
		return '<' . $this->name . $this->attributes->str() . ($sgmlMode ? '>' : '/>');
	}

	private final function xmlDecl()
	{
		$attributes = new XmlAttributes($this);
		$attributes->setAttrib('version', static::XML_VERSION);
		$attributes->setAttrib('encoding', static::CHARACTER_ENCODING);
		return '<?xml' . $attributes->str() . ' ?>' . $this->getOption(self::OPTION_LINE_BREAK);
	}

	private final function prepareContent($content, $indent, $line)
	{
		if ($line) {
			$content = str_replace(["\r\n", "\r"], "\n", $content);
			$content = explode("\n", $content);
		}
		else {
			$content = str_replace(["\r\n", "\r", "\n"], ' ', $content);
			$content = str_replace("\t", '', $content);
			return $content;
		}
		$ltrim = $this->getOption(self::OPTION_LTRIM);
		if ($ltrim) {
			foreach ($content as $i => $row) {
				$content[$i] = ltrim($row);
			}
		}
		return implode($line . $indent, $content);
	}
}