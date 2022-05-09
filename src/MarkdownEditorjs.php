<?php
namespace Hsnfirdaus;
/**
 * Markdown to EditorJS Block
 * 
 * This will convert markdown content to editorjs json block.
 * This class will not parse front-matter (yaml) header.
 * 
 * @author Muhammad Hasan Firdaus
 * @copyright 2022
 */
class MarkdownEditorjs
{
	/**
	 * @var	string	Current block type
	 **/
	private $currentBlockType;
	/**
	 * @var	string	Current block data
	 **/
	private $currentBlockData;
	/**
	 * @var	string	Previous block type
	 **/
	private $prevBlockType;
	/**
	 * @var	string	Previous block data
	 **/
	private $prevBlockData;

	/**
	 * @var	string	All block content
	 **/
	private $blocks=[];
	/**
	 * Constructor
	 * @param	string	$raw	Raw of markdown content
	 **/
	function __construct(string $raw)
	{
		$lines = preg_split("/\r\n|\n|\r/", $raw);
		
		foreach ($lines as $line) {
			$this->detectBlock($line);
		}
		$this->detectBlock('');
	}
	/**
	 * Getting all blocks as Array
	 **/
	public function getBlocks(){
		return $this->blocks;
	}
	/**
	 * Getting as editorjs JSON
	 **/
	public function getEditorjsJSON(){
		$timestamp = microtime();
		$block_version = "2.24.2";
		$array = [
			'time'		=>	$timestamp,
			'blocks'	=>	$this->blocks,
			'version'	=>	$block_version
		];
		return json_encode($array);
	}
	/**
	 * Detecting block type and parse it
	 * @param	string 	$content	Block markdown content
	 **/
	private function detectBlock(string $content){

		$this->prevBlockType=$this->currentBlockType;
		$this->prevBlockData=$this->currentBlockData;

		if (substr(trim($content), 0, 3)==='```') {

			// Code
			if($this->prevBlockType==='code'){
				$data=[
					'code'	=>	$this->prevBlockData['code']
				];

				$this->currentBlockType=NULL;
				$this->currentBlockData=NULL;

			}else{
				$data=[
					'code'	=>	''
				];

				$this->currentBlockType='code';
				$this->currentBlockData=$data;
			}
		} elseif ($this->prevBlockType==='code'&&$this->currentBlockType!==NULL){

			// Code content
			$this->currentBlockType='code';
			$data=[
				'code'	=>	$this->prevBlockData['code'].$content."\n"
			];
			$this->currentBlockData=$data;

			$this->removePrevBlock();

		}elseif(preg_match("/^([\#]{1,6})\s(.*?)$/", $content, $matches)){

			// Header block
			$this->currentBlockType='header';

			$data = [
				'text'	=>	self::styleText(htmlentities($matches[2])),
				'level'	=>	strlen($matches[1])
			];
			$this->currentBlockData = $data;

		} elseif (preg_match("/^([\=]+)$/", $content, $matches)||preg_match("/^([\-]+)$/", $content, $matches)) {

			// Header type 2
			if($this->prevBlockType==='paragraph'){

				$this->currentBlockType='header';
				$data = [
					'text'	=>	$this->prevBlockData['text'],
					'level'	=>	($matches[1][0]==='-')?2:1
				];
				$this->currentBlockData = $data;

				$this->removePrevBlock();

			}else{
				throw new \Exception('Error parsing header tag, previous line is not supported!');
			}
		} elseif(preg_match("/^[0-9]+\.\s+(.*?)$/", $content, $matches)){

			// Ordered list
			if($this->prevBlockType==='ordered-list'){
				$data = [
					'items'	=>	$this->prevBlockData['items']
				];
				$data['items'][]=self::styleText(htmlentities($matches[1]));
				
				$this->removePrevBlock();
			}else{
				$data = [
					'items' => [
						self::styleText(htmlentities($matches[1]))
					]
				];
			}

			$this->currentBlockData=$data;
			$this->currentBlockType='ordered-list';

		} elseif(preg_match("/^(\-|\*|\+)\s+(.*?)$/", $content, $matches)){

			// Unordered list
			if($this->prevBlockType==='unordered-list'){
				$data = [
					'items'	=>	$this->prevBlockData['items']
				];
				$data['items'][]=self::styleText(htmlentities($matches[2]));

				$this->removePrevBlock();
			}else{
				$data = [
					'items' => [
						self::styleText(htmlentities($matches[2]))
					]
				];
			}

			$this->currentBlockData=$data;
			$this->currentBlockType='unordered-list';

		} elseif(preg_match("/^!\[(.*?)\]\((.*?)\s?(\"(.*?)\")?\)$/", trim($content), $matches)){

			// Image
			$alt=$matches[1];
			$url=$matches[2];
			$title=@$matches[4];

			$this->currentBlockType='image';
			$data=[
				'file'	=>	[
					'url'				=>	$url
				],
				'caption'			=>	htmlentities($title?$title:$alt),
				'withBorder'		=>	FALSE,
				'withBackground'	=>	FALSE,
				'stretched'			=>	FALSE
			];
			$this->currentBlockData=$data;

		} elseif(preg_match("/^\|(.+)\|$/", trim($content), $matches)){

			// Table
			if($this->prevBlockType==='table'){
				if(preg_match("/^([\s\-\|]+)$/", $matches[1])){
					$heading = TRUE;
					$skip = TRUE;
				}else{
					$heading = $this->prevBlockData['withHeadings'];
				}
				$data=[
					'withHeadings'	=> $heading,
					'content'		=> $this->prevBlockData['content']
				];
				$this->removePrevBlock();
			}else{
				$data=[
					'withHeadings'	=> FALSE,
					'content'		=>	[]
				];
			}
			if(!@$skip){
				$split = preg_split('/\\\\.(*SKIP)(*FAIL)|\|/', $matches[1]);
				$that = $this;
				$columns = array_map(function($value) use ($that) {
					return $that::styleText(htmlentities(trim($value)));
				}, $split);
				$data['content'][]=$columns;
			}
			$this->currentBlockType='table';
			$this->currentBlockData=$data;

		} elseif(!empty(trim($content))){

			// So it's paragraph

			$this->currentBlockType='paragraph';
			$this->currentBlockData=[
				'text'	=>	self::styleText(htmlentities($content))
			];
		}else{
			$this->currentBlockData=NULL;
			$this->currentBlockType=NULL;
		}

		$this->savePrevBlock();
	}
	/**
	 * Saving previous block
	 **/
	private function savePrevBlock(){
		if(!$this->prevBlockType) return NULL;

		switch ($this->prevBlockType) {

			case 'code':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'code',
					'data'	=>	$this->prevBlockData
				];
				break;

			case 'header':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'header',
					'data'	=>	$this->prevBlockData
				];
				break;

			case 'ordered-list':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'list',
					'data'	=>	[
						'style'	=>	'ordered',
						'items'	=>	$this->prevBlockData['items']
					]
				];
				break;

			case 'unordered-list':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'list',
					'data'	=>	[
						'style'	=>	'unordered',
						'items'	=>	$this->prevBlockData['items']
					]
				];
				break;

			case 'image':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'image',
					'data'	=>	$this->prevBlockData
				];
				break;

			case 'table':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'table',
					'data'	=>	$this->prevBlockData
				];
				break;

			case 'paragraph':
				$push = [
					'id'	=>	self::randomString(),
					'type'	=>	'paragraph',
					'data'	=>	$this->prevBlockData
				];
				break;
			
			default:
				throw new \Exception("Unable proccess block type: ".$this->prevBlockType);
				break;
		}
		$this->blocks[]=$push;
		$this->removePrevBlock();
	}
	/**
	 * Format styling parser
	 * 
	 * Parse text style: bold, italic, underline
	 * 
	 * @param	string 	$text	Text that will formated
	 * @return	string 			Formated string
	 **/
	private static function styleText(string $text){
		// Bold and italic
		$text = preg_replace("/\b(?<!\\\\)\*\*\*(.*?)(?<!\\\\)\*\*\*\b/", "<b><i>$1</i></b>", $text);
		$text = preg_replace("/\b(?<!\\\\)___(.*?)(?<!\\\\)___\b/", "<b><i>$1</i></b>", $text);

		// Bold
		$text = preg_replace("/\b(?<!\\\\)\*\*(.*?)(?<!\\\\)\*\*\b/", "<b>$1</b>", $text);
		$text = preg_replace("/\b(?<!\\\\)__(.*?)(?<!\\\\)__\b/", "<b>$1</b>", $text);

		// Italic
		$text = preg_replace("/\b(?<!\\\\)\*(.*?)(?<!\\\\)\*\b/", "<i>$1</i>", $text);
		$text = preg_replace("/\b(?<!\\\\)_(.*?)(?<!\\\\)_\b/", "<i>$1</i>", $text);

		// Inline code
		$text = preg_replace("/(?<!\\\\)`(.*?)(?<!\\\\)`/", "<code class=\"inline-code\">$1</code>", $text);

		// Link
		$text = preg_replace_callback("/\[(.*?)\]\((.*?)\s?(\"(.*?)\")?\)/", function($m){
			$content = $m[1];
			$url = $m[2];
			$title = @$m[4];
			$attr = $title?' title="'.$title.'"':'';
			return '<a href="'.$url.'"'.$attr.'>'.$content.'</a>';
		}, $text);
		$text = preg_replace("/<([a-zA-Z-]*?):\/\/(.*?)>/", "<a href=\"$1://$2\">$1://$2</a>", $text);

		// Remove backslash
		$text = preg_replace("/(?<!\\\\)\\\/","", $text);
		return $text;
	}
	/**
	 * Randomizen string for block id
	 * Using rand function of php
	 * 
	 * @param	int	$length	Length of random id
	 * @return	string	The random ID
	 **/
	private static function randomString(int $length=10){
		$string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';
		$stringLength = strlen($string);
		$result='';
		for ($i=0; $i < $length; $i++) { 
			$random = rand(0, $stringLength-1);
			$result.=$string[$random];
		}
		return $result;
	}
	/**
	 * Removing previous block from variable
	 **/
	private function removePrevBlock(){
		$this->prevBlockData=NULL;
		$this->prevBlockType=NULL;
	}
}