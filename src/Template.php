<?php

namespace ByJG\JinjaPhp;

use ByJG\JinjaPhp\Undefined\DefaultUndefined;
use ByJG\JinjaPhp\Undefined\StrictUndefined;

class Template
{

    protected $template = null;
    protected $undefined = null;
    protected $variables = [];

    public function __construct($template)
    {
        $this->template = $template;
        $this->undefined = new StrictUndefined();
    }

    public function withUndefined($undefined)
    {
        $this->undefined = $undefined;
        return $this;
    }

    public function render($variables = [])
    {
        $this->variables = $variables;
        return $this->renderTemplate($this->template);
    }

    protected function renderTemplate($template, $variables = [])
    {
        $variables = $this->variables + $variables;
        return $this->parseVariables(
            $this->parseIf(
                $this->parseFor($variables),
                $variables),
            $variables
        );
    }

    protected function getVar($varName, $variables, $undefined = null) {
        $varAr = explode('.', trim($varName));
        $varName = $varAr[0];
        if (isset($variables[$varName])) {
            if (count($varAr) > 1) {
                return $this->getVar(implode(".", array_slice($varAr, 1)), $variables[$varName], $undefined);
            }
            return $variables[$varName];
        } else {
            if (is_null($undefined)) {
                $undefined = $this->undefined;
            }
            return $undefined->render($varName);
        }
    }
    
    protected function extractFilterValues($filterCommand) {
        $regex = '/([a-zA-Z0-9_-]+)\s*(\((.*)\))?/';
        preg_match($regex, $filterCommand, $matches);
        $filterName = $matches[1];
        // Split the parameters by comma, but not if it is inside quotes
        if (isset($matches[3])) {
            // get filter parameters without parenthesis and delimited by , (comma) not (inside quotes or single quotes)
            // $filterParams = preg_split('/(?<=[^\'"]),(?=[^\'"])/', $matches[3]);
            if (preg_match_all("~'[^']++'|\([^)]++\)|[^,]++~", $matches[3], $filterParams)) {
                $filterParams = $filterParams[0];
            } else {
                $filterParams = [];
            }
        } else {
            $filterParams = [];
        }
        return [$filterName, $filterParams];
    }
    
    protected function applyFilter($values, $variables) {
        $content = trim(array_shift($values));
        $firstTime = true;
        do {
            $filterCommand = $this->extractFilterValues(array_shift($values));
            if ($firstTime) {
                $firstTime = false;
                if ($filterCommand[0] == "default") {
                    $default = isset($filterCommand[1][0]) ? $this->evaluateVariable($filterCommand[1][0], $variables) : "";
                    $content = $this->evaluateVariable($content, $variables, new DefaultUndefined($default));
                    continue;
                } else {
                    $content = $this->evaluateVariable($content, $variables);
                }
            }

            switch ($filterCommand[0]) {
                case "upper":
                    $content = strtoupper($content);
                    break;
                case "lower":
                    $content = strtolower($content);
                    break;
                case "join":
                    $delimiter = isset($filterCommand[1][0]) ? $this->evaluateVariable($filterCommand[1][0], $variables) : "";
                    $content = implode($delimiter, (array)$content);
                    break;
                case "replace":
                    $search = isset($filterCommand[1][0]) ? $this->evaluateVariable($filterCommand[1][0], $variables) : "";
                    $replace = isset($filterCommand[1][1]) ? $this->evaluateVariable($filterCommand[1][1], $variables) : "";
                    $content = str_replace($search, $replace, $content);
                    break;
                case "split":
                    $delimiter = isset($filterCommand[1][0]) ? $this->evaluateVariable($filterCommand[1][0], $variables) : "";
                    $content = explode($delimiter, $content);
                    break;
            }
        } while (count($values) > 0);
        return $content;
    }
    
    protected function evaluateVariable($content, $variables, $undefined = null) {
        if (strpos($content, ' | ') !== false) {
            return $this->applyFilter(explode(" | ", $content), $variables);
        // } else if (strpos($content, ' is ') !== false) {
        //     $content = "{{ " . str_replace(' is ', '}}{{', $content) . " }}";
        //     return $this->parseVariables($content, $variables);
        // } else if (strpos($content, ' in ') !== false) {
        //     $content = "{{ " . str_replace(' in ', '}}{{', $content) . " }}";
        //     return $this->parseVariables($content, $variables);       
        } else if (preg_match('/\s*~\s*/', $content) ) {
            $array = preg_split('/\s*~\s*/', $content);
            for ($i = 0; $i < count($array); $i++) {
                $array[$i] = $this->evaluateVariable($array[$i], $variables);
            }
            return implode("", $array);
        } else if (preg_match('/^["\'].*["\']$/', trim($content)) || is_numeric(trim($content)) || trim($content) == "true" || trim($content) == "false") {
            $valueToEvaluate = $content;
        // parse variables inside parenthesis
        } else if (preg_match('/\((.*)\)/', $content, $matches)) {
            $content = preg_replace_callback('/\((.*)\)/', function($matches) use (&$valueToEvaluate, $variables) {
                return $this->evaluateVariable($matches[1], $variables);
            }, $content);
            $valueToEvaluate = $this->evaluateVariable($content, $variables);
        // match content with the array representation
        } else if (preg_match('/^\[.*\]$/', trim($content))) {
            $array = preg_split('/\s*,\s*/', trim($content, "[]"));
            $retArray = [];
            for ($i = 0; $i < count($array); $i++) {
                $arData = preg_split('/\s*:\s*/', $array[$i]);
                if (count($arData) == 2) {
                    $retArray[trim($arData[0], "\"'")] = $this->evaluateVariable($arData[1], $variables);
                } else {
                    $retArray[$i] = $this->evaluateVariable($array[$i], $variables);
                }
            }
            return $retArray;
        } else if (preg_match('/(<=|>=|==|!=|<>|\*\*|&&|\|\||[\+\-\/\*\%\<\>])/', $content) ) {
            $array = preg_split('/(<=|>=|==|!=|<>|\*\*|&&|\|\||[\+\-\/\*\%\<\>])/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 0; $i < count($array); $i=$i+2) {
                $array[$i] = $this->evaluateVariable($array[$i], $variables);
                if (is_string($array[$i])) {
                    $array[$i] = "'" . $array[$i] . "'";
                } else if (is_bool($array[$i])) {
                    $array[$i] = $array[$i] ? "true" : "false";
                }
            }
            $valueToEvaluate = implode(" ", $array);
        } else if (preg_match("/^!/", trim($content))) {
            $valueToEvaluate = $content;

        } else {
            $var = $this->getVar($content, $variables, $undefined);
            if (is_array($var)) {
                return $var;
            }
            $valueToEvaluate = "'" . $this->getVar($content, $variables, $undefined) . "'";
        }

        if (is_bool($valueToEvaluate)) {
            return $valueToEvaluate;
        }
    
        $evalResult = "";
        eval("\$evalResult = $valueToEvaluate;");
        return $evalResult;
    }

    protected function prepareDocumentToParse($partialTemplate, $startTag, $endTag)
    {
        // count the number of {% $startTag %} and {% $endTag %} tags using regex
        $regex = '/\{%\s*' . $startTag . '(.*)\%}/sU';
        preg_match_all($regex, $partialTemplate, $matches);
        $startTagCount = count($matches[0]);
        $regex = '/\{%\s*' . $endTag . '\s*\%}/sU';
        preg_match_all($regex, $partialTemplate, $matches);
        $endTagCount = count($matches[0]);
        if ($startTagCount != $endTagCount) {
            throw new \Exception("The number of {% $startTag %} and {% $endTag %} tags does not match");
        }

        if ($startTagCount == 0) {
            return [0, $partialTemplate];
        }

        // find all {% $startTag %} and replace then with {% $startTag00%} where 00 can be 01, 02, 03, etc.
        $iStartTag = 0;
        $iEndTag = [];
        $result = $partialTemplate;

        // Close the closest {% $endTag %} tags before opening a new {% $startTag %} tag
        $fixArray = function ($iEndTag, $endTag, $result) {
            $iEndTag = array_reverse($iEndTag);

            foreach ($iEndTag as $i) {
                $regex = '/\{%\s*' .  $endTag . '\s*\%}/sU';
                $result = preg_replace_callback($regex, function ($matches) use ($i, $endTag) {
                    return '{% ' .  $endTag . str_pad($i, 2, "0", STR_PAD_LEFT) . " %}";
                }, $result, 1);
            }

            $iEndTag = [];

            return [$iEndTag, $result];
        };

        while ($iStartTag < $startTagCount) {
            $regex = '/\{%\s*' . $startTag . ' /sU';
            $iStartTag++;
            $result = preg_replace_callback($regex, function ($matches) use ($iStartTag, $startTag) {
                return '{% ' . $startTag . str_pad($iStartTag, 2, "0", STR_PAD_LEFT) . " ";
            }, $result, 1);

            $iPosStartTag = strpos($result, '{% ' . $startTag . str_pad($iStartTag, 2, "0", STR_PAD_LEFT) . " ");
            $iPosStartTagAfter = preg_match('/\{%\s*' . $startTag . ' /sU', $result, $matchesTmpStartTag, PREG_OFFSET_CAPTURE, $iPosStartTag);
            $iPosEndTag = preg_match('/\{%\s*' .  $endTag . '\s*\%}/sU', $result, $matchesTmpEndTag, PREG_OFFSET_CAPTURE, $iPosStartTag);

            if ($iPosStartTagAfter && $iPosEndTag && $matchesTmpEndTag[0][1] < $matchesTmpStartTag[0][1]) {
                $result = preg_replace_callback('/\{%\s*' .  $endTag . '\s*\%}/sU', function ($matches) use ($iStartTag, $endTag) {
                    return '{% ' .  $endTag . str_pad($iStartTag, 2, "0", STR_PAD_LEFT) . " %}";
                }, $result, 1);

                list($iEndTag, $result) = $fixArray($iEndTag, $endTag, $result);
            } else {
                $iEndTag[] = $iStartTag;
            }
        }

        list($iEndTag, $result) = $fixArray($iEndTag, $endTag, $result);

        return [$startTagCount, $result];
    }
    
    protected function parseIf($partialTemplate, $variables = [])
    {
        list($ifCount, $result) = $this->prepareDocumentToParse($partialTemplate, "if", "endif");
 
        // Find {%if%} and {%endif%} and replace the content between them
        for ($i=1; $i <= $ifCount; $i++) {
            $position = str_pad($i, 2, "0", STR_PAD_LEFT);

            $regex = '/\{%\s*if' . $position . '(.*)\%}(.*)\{%\s*endif' . $position . '\s*\%}/sU';
            $result = preg_replace_callback($regex, function ($matches) use ($variables) {
                $condition = trim($matches[1]);
                $ifContent = $matches[2];
                $ifParts = preg_split('/\{%\s*else\s*\%}/', $ifContent);
                if ($this->evaluateVariable($condition, $variables)) {
                    return $ifParts[0];
                } else if (isset($ifParts[1])) {
                    return $ifParts[1];
                }
                return "";
            }, $result);
        }
        return $result;
    }
    
    protected function parseFor($variables)
    {
        // Find {%for%} and {%endfor%} and replace the content between them
        $regex = '/\{%\s*for(.*)\s*\%}(.*)\{%\s*endfor\s*\%}/sU';
        $result = preg_replace_callback($regex, function ($matches) use ($variables) {
    
            $content = "";
            $regexFor = '/\s*(?<key1>[\w\d_-]+)(\s*,\s*(?<key2>[\w\d_-]+))?\s+in\s+(?<array>.*)\s*/';
            $forExpression = trim($matches[1]);
            if (preg_match($regexFor, $forExpression, $matchesFor)) {
                $array = $this->evaluateVariable($matchesFor["array"], $variables);
                if (!empty($matchesFor["key2"])) {
                    $forKey = $matchesFor["key1"];
                    $forValue = $matchesFor["key2"];
                } else {
                    $forKey = "__index";
                    $forValue = $matchesFor["key1"];
                }
                $index = 0;
                $loop = [];
                foreach ($array as $key => $value) {
                    $loop["first"] = $index == 0;
                    $loop["last"] = $index == count($array) - 1;
                    $loop["index"] = $index + 1;
                    $loop["index0"] = $index;
                    $loop["revindex"] = count($array) - $index;
                    $loop["revindex0"] = count($array) - $index - 1;
                    $loop["length"] = count($array);
                    $loop["even"] = $index % 2 == 0;
                    $loop["odd"] = $index % 2 == 1;
                    
                    $content .= $this->parseVariables($matches[2], $variables + [$forKey => $key, $forValue => $value] + ["loop" => $loop]);
                    $index++;
                }
            }
    
            return $content;
        }, $this->template);
        return $result;
    }
    
    
    protected function parseVariables($partialTemplate, $variables) {
        // Find {{}} and replace the content between them
        $regex = '/\{\{(.*)\}\}/U';
        $result = preg_replace_callback($regex, function ($matches) use ($variables) {
            // if contains any math operation, evaluate it
            return $this->evaluateVariable($matches[1], $variables);
        }, $partialTemplate);
        return $result;
    }

}