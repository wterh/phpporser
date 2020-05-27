<?php

namespace Parser;

class Dom{

	private $sourceText;
	private $dom;
	private $__ENABLE_COMMENTS = false;
    private $__ESCAPE_CLOSING_TAGS = false;
    private $__ESCAPE_SYMBOLS = ["\n"," ", "\t", "\e", "\f", "\v", "\r"];
    private $__RECURTION_INPUTS = 0;
    private $__RECURTION_ERROR_OUTPUT = false;
    private $__DEBUG_MODE = false;

	function __construct($url, $enable_comments = false){
		if($enable_comments){
			$this->__ENABLE_COMMENTS = true;
		}
		if($this->sourceText){
			throw new Exception('sourceText exists!');
		}else{
			if(preg_match('/http(s)*:\/\//i', $url)){
				$this->sourceText = $this->query($url);
			}else{
				$this->sourceText = file_get_contents($url);
				return true;
			}
		}
	}

	public function dom(){
		if(!$this->dom) $this->dom = $this->pars($this->sourceText)[0];
		return $this->dom;
	}

	private function space_jitter($position, $text, $s_pointer = false) : int{
			if(!$s_pointer){
				$s_pointer = strlen($text);
			}
			for($i = $position+1;$i < $s_pointer;$i++){
				if($text[$i] != ' ') return $i;
			}
			return false;
		}

	private function is_singleton(string $tag) : bool{
			$non_close_tags = ['area','base','basefont','bgsound','br','hr','col','command','embed','img','input','isindex','keygen','link','meta','param','source','track','wbr','!DOCTYPE', 'use', 'path'];
			for($i = 0;$i < count($non_close_tags);$i++){
				if(strcasecmp($tag,$non_close_tags[$i]) == 0){
					return true;
				}
			}
			unset($non_close_tags);
			return false;
		}

	private function escape_symbols(array $symb, string $text){
			$result = '';
			$finded = false;
			for($i = 0;$i < strlen($text);$i++){
				for($j = 0;$j < count($symb);$j++){
					if($text[$i] == $symb[$j]){
						$finded = true;
					}
				}
				if($finded == true){
					$finded = false;
				}else{
					$result .= $text[$i];
				}
			}
			return $result;
	}

	private function stack_recurtion(array $stack, int $pointer, array $main_tag = [], array $open_tags = []){
				$result = [];
				for($i = $pointer;$i < count($stack);$i++){
					$current_tag = $stack[$i];
					if((!$current_tag['is_singleton']) && (!$current_tag['is_closing']) && ($current_tag['tag'] != '__TEXT' && $current_tag['tag'] != '__COMMENT')){

						$point = $this->node_tag_tracker($open_tags, $current_tag['tag']);
						if(!$open_tags[$point]){
							$open_tags[$point]['tag'] = $current_tag['tag'];
							$open_tags[$point]['count'] = 1;
						}else{
							$open_tags[$point]['count']++;
						}

						$dependend_tags = $this->stack_recurtion($stack, $i+1, $current_tag, $open_tags);
						$open_tags = $dependend_tags[2];

						array_push($current_tag, $dependend_tags[0]);
						array_push($result,$current_tag);

						$this->__RECURTION_INPUTS++;
						$i = $dependend_tags[1];
					}

					if($current_tag['tag'] == '__TEXT' || $current_tag['tag'] == '__COMMENT'){
						$result[] = $current_tag;
					}
					if($current_tag['is_singleton']){
						array_push($result, $current_tag);
					}else if($current_tag['is_closing']){
					  if($main_tag['tag'] != $current_tag['tag']){
					  	$finded = false;
						  	for($h = 0;$h < count($open_tags);$h++){
						  		if($open_tags[$h]['count'] > 0 && $open_tags[$h]['tag'] == $main_tag['tag']){
						  			for($z = 0;$z < count($open_tags);$z++){
						  				if($open_tags[$z]['count'] > 0 && $open_tags[$z]['tag'] == $current_tag['tag']){
						  					$finded = $h;
						  				}
						  			}
						  		}
						  	}
					  		if($finded){
					  			$open_tags[$finded]['count']--;
					  			$splice_tag = ['tag' => $open_tags[$finded]['tag'], 'is_closing' => true];
								array_splice($result, $i, 0, [$splice_tag]);
								if($finded['count'] == 0){
									return [$result, $i-1, $open_tags];
								}else{
									return [$result, $i-1, $open_tags];
								}
					  		}else{
					  			continue;
					  		}
					  }
                      array_push($result, $current_tag);
                      $point = $this->node_tag_tracker($open_tags, $current_tag['tag']);
                      $open_tags[$point]['count']--;
						return [$result, $i, $open_tags];
					}

				}
				return [$result, $i, $open_tags];
			}

		private function escape_comments($html, $position = 0){
					$result = "";
					$open_tag_position = 0;
          			$html_strlen = strlen($html);
					for($i = $position;$i < $html_strlen;$i++){
						if($html[$i] == "<" && $html[$i + 1] == "!" && $html[$i + 2] == "-" && $html[$i + 3] == "-"){
							$open_tag_position = $i+3;
							$result .= $html[$i];
						}
						else if($html[$i] == ">" && $html[$i - 1] == "-" && $html[$i - 2] == "-" && $open_tag_position < $i-2){
							$result .= $html[$i];
							return [$i+1, $result];
						}else{
							$result .= $html[$i];
						}
					}

				}

		private function read_tag(string $html, int $f_pointer = 0, int $s_pointer = 0) : array{

		if(!$s_pointer){
			$s_pointer = strlen($html);
		}

		$result = ['is_closing' => false, 'is_singleton' => false];

		$is_comment_tag = false;
		$tag= '';
		$attribute='';
		$value = '';
		$state = 'tag';
		$bracket = 0;
		$bracket_count = 0;
		$closed_tag = false;
		$without_parth = false;

		for($i = $f_pointer;$i < $s_pointer;$i++, $result['pointer'] = $i){
			if($closed_tag) break;
			if($i == $s_pointer-1 && $html[$i] != '>') throw new Exception('Cannot find \'>\' symbol');
			switch($html[$i]){
				case ' ':
				if($state == 'tag' && $tag != false){
					$result['tag'] = $this->escape_symbols($this->__ESCAPE_SYMBOLS, $tag);
					$state = 'attribute';
				}else if($state == 'attribute_value'){
					if($without_parth){
						$result[$attribute] = $value;
						$value = false;
						$attribute = false;
						$state = 'attribute';
					}else{
						$value .= $html[$i];
					}				
				}else if($state == 'attribute' && $attribute != false){
					$symbol = $html[$this->space_jitter($i, $html, $s_pointer)];
					if($symbol == '='){
						continue;
					}else{
						$result[$attribute] = true;
						$state = 'attribute';
						$attribute = false;
						$value = false;
						$bracket = 0;
						$bracket_count = 0;
					}
					$i = $this->space_jitter($i, $html, $s_pointer)-1;
				}
				break;
				case '>':
				switch($state){
					case 'tag':
					if($tag != false) $result['tag'] = $this->escape_symbols($this->__ESCAPE_SYMBOLS, $tag);
					$closed_tag = true;
					break;
					case 'attribute_value_starting':
					return ['error_code' => 2, 'Unknown attribute value in '.$i.' symbol'];
					break;
					case 'attribute_value':
					if($without_parth){
						$result[$attribute] = $value;
						$value = false;
						$attribute = false;
						$closed_tag = true;
					}else{
						$value .= $html[$i];
					}
					continue;
					break;
					case 'attribute':
					if($attribute != false){
						$result[$attribute] = true;
					}
					$closed_tag = true;
					break;
				}
				if(($state == 'tag' || $state == 'attribute') && $this->is_singleton($this->escape_symbols($this->__ESCAPE_SYMBOLS, $tag)) ){
				 $result['is_singleton'] = true;
				}
				break;
				case '\'':
				if($state == 'attribute_value_starting' && $attribute && $bracket_count == 0){
					$state = 'attribute_value';
					$bracket = 2;
					$bracket_count++;
				}else if($bracket_count == 1 && $bracket == 2){
					$result[$attribute] = $value;
					$state = 'attribute';
					$attribute = false;
					$value = false;
					$bracket = 0;
					$bracket_count = 0;
				}else{
					$value .= $html[$i];
				}
				break;
				case '"':
				if($state == 'attribute_value_starting' && $attribute && $bracket_count == 0){
					$state = 'attribute_value';
					$bracket = 1;
					$bracket_count++;
				}else if($bracket_count == 1 && $bracket == 1){
					$result[$attribute] = $value;
					$state = 'attribute';
					$attribute = false;
					$value = false;
					$bracket = 0;
					$bracket_count = 0;
				}else{
					$value .= $html[$i];
				}
				break;
				case '=':
				if($state == 'attribute' && $attribute){
					$state = 'attribute_value_starting';
					}else if($state =='attribute_value_starting'){
						return ['error_code' => 3, 'Html syntax error '];
					}else if($state == 'attribute_value'){
						$value .= $html[$i];
					}
					break;
					case '/':
					if($state == 'tag'){
						$result['is_closing'] = true;
					}else if($state == 'attribute_value'){
						$value.= $html[$i];
					}else if($html[$this->space_jitter($i, $html, $s_pointer)] == '>'){
						$result['is_closing'] = true;
						$i = $this->space_jitter($i, $html, $s_pointer)-1;
					}
					break;
					case '<':
					if($state == 'attribute_value'){
						$value .= $html[$i];
					}else if($state == 'attribute'){
						return ['error_code' => 1, 'Html syntax error '.$i.' '.$html[$i].$html[$i+1].$html[$i+2].$html[$i+3]];
					}
					break;
					default:
					switch($state){
						case 'attribute':
						$attribute .= $html[$i];
						break;
						case 'attribute_value':
						$value .= $html[$i];
						break;
						case 'tag':
						$tag .= $html[$i];
						break;
						case 'attribute_value_starting':
							$without_parth = true;
							$state = 'attribute_value';
							$value .= $html[$i];
						break;
					}
					break;
				}
			}
			if(isset($result['id'])) $result['id'] = explode(' ', $result['id']);
			if(isset($result['class'])) $result['class'] = explode(' ', $result['class']);
			return $result;
		}

	private function node(string $html, int $f_pointer = 0, int $s_pointer = 0) : array {
				$lenght = strlen($html);

				if(!$s_pointer){
					$s_pointer = $lenght;
				}

				$result;
				$stack = [];
				$level = 0;
				$text='';
				$text_stack = [];
				$ignore_html = false;

				for($i = 0;$i < $lenght;$i++){
					if($html[$i] == '<' && $this->escape_symbols(["<","\n"," ", "\t", "\e", "\f", "\v", "\r"], $html[$i+1])){
						if($this->escape_symbols(["a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","/", "!"], $html[$i+1])){
							$text .= $html[$i];
							$text .= $html[$i+1];
							$i++;
							continue;
						}
						if($html[$i + 1] == "!" && $html[$i + 2] == "-" && $html[$i + 3] == "-"){
							$comment = $this->escape_comments($html, $i);
							if($this->__ENABLE_COMMENTS == true){
								 $stack[] = ['tag' => '__COMMENT', htmlspecialchars($comment[1])];
							}
							$i = $comment[0]-1;
							continue;
						}
						$temporary_tag = $this->read_tag($html, $i);
						if($temporary_tag['error_code'] && $ignore_html){
							continue;
						}else
						if($temporary_tag['error_code'] && !$ignore_html){
							throw new Exception($temporary_tag[0]);
						}
						if(($temporary_tag['tag'] == 'script' || $temporary_tag['tag'] == 'style') && $temporary_tag['is_closing'] == 0){
							$ignore_html = true;
						}else if(($temporary_tag['tag'] == 'script' || $temporary_tag['tag'] == 'style') && $temporary_tag['is_closing'] == 1){
							$ignore_html = false;
						}
						if($ignore_html == false){
							if($temporary_tag['is_singleton']){
								$stack[] = $temporary_tag;
							}else{
								if(($temporary_tag['tag'] != 'script') && ($temporary_tag['tag'] != 'style')){
									if($this->escape_symbols($this->__ESCAPE_SYMBOLS, $text)){
										$stack[] = ['tag' => '__TEXT',htmlspecialchars($text)];
										$text = '';
									}else if($this->escape_symbols($this->__ESCAPE_SYMBOLS, $text) == '0'){
										$stack[] = ['tag' => '__TEXT',htmlspecialchars($text)];
										$text = '';
									}
									$stack[] = $temporary_tag;
								}else{
									$text = '';
								}
							}

							$i = $temporary_tag['pointer']-1;
						}
					}else{
						$text .= $html[$i];
					}
				}
				if($this->__DEBUG_MODE){
					return $stack;
				}else{
					return $this->stack_recurtion($stack, 0);
				}
			}

	private function pars(string $html){
		return $this->node($html);
	}

	public function read() : string{
		return $this->sourceText;
	}

	public function dump() {
		return htmlspecialchars($this->sourceText);
	}

	public function node_tag_tracker($array ,string $tag){
		for($i = 0;$i < count($array);$i++){
			if($array[$i]['tag'] == $tag){
				return $i;
			}
		}
		return count($array);
	}

	public function find(string $element, $number = -1){
		if(!$this->dom) $this->dom = $this->pars($this->sourceText);
		return new Element($this->dom[0], $element, $number);
	}

	private function query(string $url): string {
		$curlSession = curl_init();

	    curl_setopt($curlSession, CURLOPT_HEADER, 0);
	    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($curlSession, CURLOPT_URL, $url);
	    curl_setopt($curlSession, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	    curl_setopt($curlSession, CURLOPT_FOLLOWLOCATION, TRUE);
	    curl_setopt($curlSession, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));

	    $returningData = curl_exec($curlSession);
	    curl_close($curlSession);

	    return $returningData;
	}

}

class Element{

	private $__DOM = [];
	private $__ELEMENT = '';
	private $__ELEMENT_TYPE = '';
	private $__POINTS = [];
	private $__ELEMENT_DOM = [];
	private $__ELEMENT_NUMBER = 0;

	function __construct(array $dom, string $element, int $number){
		$this->__DOM = $dom;
		$this->__ELEMENT = str_replace(' ', '',$element);
		switch ($this->__ELEMENT[0]){
			case '.':
				$this->__ELEMENT_TYPE = 'class';
				$this->__ELEMENT = str_replace('.', '',$element);
			break;
			case '#':
				$this->__ELEMENT_TYPE = 'id';
				$this->__ELEMENT = str_replace('#', '',$element);
			break;
			default:
				$this->__ELEMENT_TYPE = 'tag';
			break;	
		}
		$this->__ELEMENT_DOM = $this->parsDom($this->__DOM, $number)[0];
		print_r($this->parsDom($this->__DOM, $number)[1].' ');
	}

	private function parsDom($dom = false, $number = -1, $point = 0){
		if(!$dom) $dom = $this->__DOM;
		$temporary_dom = [];
		$dom = $this->one_dom($dom);
		for($i = 0;$i < count($dom);$i++){
			if($this->__ELEMENT_TYPE == 'tag'){
				if($dom[$i]['tag'] == '__COMMENT' || $dom[$i]['tag'] == '__TEXT') continue;
				if($dom[$i]['tag'] != $this->__ELEMENT && $dom[$i]['is_closing'] != true){
					if($dom[$i][0]){
						$obj = $this->parsDom($dom[$i],$number, $point);
						if($obj[0]){
							if(isset($obj[1]) == false){
								return [$obj];
							}else{
								$point = $obj[1];
								array_push($temporary_dom, $obj[0]);
							}
						}
					}
				}else if($dom[$i]['is_closing'] != true){
					$obj = $this->parsDom($dom[$i],$number, $point);
					if($point == $number-1){
						return [$dom[$i][0]];
					}
					if(isset($obj[1]) == false){
						return [$obj];
					}else{
						$point = $obj[1]+1;
						if($dom[$i]['is_singleton']){
							array_push($temporary_dom, $dom[$i]);
						}else{
							array_push($temporary_dom, $dom[$i][0]);
						}
					}
				}
			}
			if($this->__ELEMENT_TYPE == 'id'){
				if($dom[$i]['tag'] == '__COMMENT' || $dom[$i]['tag'] == '__TEXT') continue;
				if(!$dom[$i]['id']){
					if($dom[$i][0]){
						$obj = $this->parsDom($dom[$i],$number, $point);
						if($obj[0]){
							if(isset($obj[1]) == false){
								return [$obj];
							}else{
								$point = $obj[1];
								array_push($temporary_dom, $obj[0]);
							}
						}
					}
				}else{
					$finded = false;
					for($j = 0;$j < count($dom[$i]['id']);$j++){
						if($dom[$i]['id'][$j] == $this->__ELEMENT){
							$finded = true;
						}
					}
					if($finded){
						$obj = $this->parsDom($dom[$i][0],$number, $point);
						if($point == $number-1){
							return [$dom[$i][0]];
						}
						if(isset($obj[1]) == false){
							return [$obj];
						}else{
							$point = $obj[1]+1;
							array_push($temporary_dom, $dom[$i][0]);
						}
					}else{
						if($dom[$i][0]){
							$obj = $this->parsDom($dom[$i],$number, $point);
							if($obj[0]){
								if(isset($obj[1]) == false){
									return [$obj];
								}else{
									$point = $obj[1];
									array_push($temporary_dom, $obj[0]);
								}
							}
						}
					}
				}
			}
			if($this->__ELEMENT_TYPE == 'class'){
				if($dom[$i]['tag'] == '__COMMENT' || $dom[$i]['tag'] == '__TEXT') continue;
				if(!$dom[$i]['class']){
					if($dom[$i][0]){
						$obj = $this->parsDom($dom[$i],$number, $point);
						if($obj[0]){
							if(isset($obj[1]) == false){
								return [$obj];
							}else{
								$point = $obj[1];
								array_push($temporary_dom, $obj[0]);
							}
						}
					}
				}else{
					$finded = false;
					for($j = 0;$j < count($dom[$i]['class']);$j++){
						if($dom[$i]['class'][$j] == $this->__ELEMENT){
							$finded = true;
						}
					}
					if($finded){
						$obj = $this->parsDom($dom[$i][0],$number, $point);
						if($point == $number-1){
							return [$dom[$i][0]];
						}
						if(isset($obj[1]) == false){
							return [$obj];
						}else{
							$point = $obj[1]+1;
							array_push($temporary_dom, $dom[$i][0]);
						}
					}else{
						if($dom[$i][0]){
							$obj = $this->parsDom($dom[$i],$number, $point);
							if($obj[0]){
								if(isset($obj[1]) == false){
									return [$obj];
								}else{
									$point = $obj[1];
									array_push($temporary_dom, $obj[0]);
								}
							}
						}
					}
				}
			}
		}
		return [$temporary_dom, $point];
	}

	public function viewDom(){
		return $this->__ELEMENT_DOM;
	}

	public function getElementType(){
		return $this->__ELEMENT_TYPE;
	}

	private function one_dom(array $dom){
		$is_empty = true;
		$is_empty_dom = $dom;
		while(count($is_empty_dom) <= 1){
			$is_empty_dom = $is_empty_dom[0];
		}
		return $is_empty_dom;
	}

	public function plainText(){
		$result = [];
		for($i = 0;$i < count($this->__ELEMENT_DOM);$i++){
			for($j = 0;$j < count($this->__ELEMENT_DOM[$i]);$j++){
				if($this->__ELEMENT_DOM[$i][$j]['tag'] == '__TEXT'){
					array_push($result, $this->__ELEMENT_DOM[$i][$j][0]);
				}
			}
		}
		return $result;
	}

	public function find(string $element, int $number = -1){
		return new Element([$this->__ELEMENT_DOM], $element);
	}

	public function children(int $number){
		$result = [];
		array_push($result, $this->__ELEMENT_DOM[$number]);
		return new Children($result);
	}
}

Class Children{
	private $__DOM = [];

	function __construct($dom){
		if($dom[0]){
			$this->__DOM = $this->one_dom($dom);
		}else{
			throw new \Exception("Children not found");
		}
	}

	public function find(string $element){
		return new Element($this->__DOM, $element);
	}

	public function viewDom(){
		return $this->__DOM;
	}

	public function children(int $number) {
		$result = [];
		array_push($result, $this->__DOM[$number]);
		return new Children($result);
	}

	public function plainText(){
		$result = [];
		if($this->__DOM['tag']) return $this->__DOM[0];
		for($i = 0;$i < count($this->__DOM);$i++){
			for($j = 0;$j < count($this->__DOM[$i]);$j++){
				if($this->__DOM[$i][$j]['tag'] == '__TEXT'){
					array_push($result, $this->__DOM[$i][$j][0]);
				}
			}
		}
		return $result;
	}

	private function one_dom(array $dom){
		$is_empty = true;
		$is_empty_dom = $dom;
		while(count($is_empty_dom) <= 1 && !$is_empty_dom['tag']){
			$is_empty_dom = $is_empty_dom[0];
		}
		print_r(count($is_empty_dom));
		return $is_empty_dom;
	}
}

?>
