<?php

namespace GeorgeZakharov\FpdfTablesExtended;

class StringTags
{
    /**
     * Contains the Tag/String Correspondence
     *
     * @access    protected
     * @var        array
     */
    protected $aTAGS = array();

    /**
     * Contains the links for the tags that have specified this parameter
     *
     * @access    protected
     * @var        array
     */
    protected $aHREF;

    /**
     * The maximum number of chars for a tag
     *
     * @access    protected
     * @var        integer
     */
    protected $iTagMaxElem;

    /**
     * @param int $p_tagmax - the number of characters allowed in a tag
     */
    public function __construct($p_tagmax = 10)
    {
        $this->aTAGS = array();
        $this->aHREF = array();
        $this->iTagMaxElem = $p_tagmax;
    }

    /**
     * Returns TRUE if the specified tag name is an "<open tag>", (it is not already opened)
     *
     * @access    protected
     * @param    string $p_tag - tag name
     * @param    array $p_array - tag arrays
     * @return    boolean
     */
    protected function OpenTag($p_tag, $p_array)
    {
        $aTAGS = &$this->aTAGS;
        $aHREF = &$this->aHREF;
        $maxElem = &$this->iTagMaxElem;

        if (!preg_match('/^<([a-zA-Z1-9]{1,' . $maxElem . '}) *(.*)>$/', $p_tag, $reg)) {
            return false;
        }

        $p_tag = $reg[1];

        $sHREF = array();
        if (isset($reg[2])) {
            preg_match_all("|([^ ]*)=[\"'](.*)[\"']|U", $reg[2], $out, PREG_PATTERN_ORDER);
            $outCount = count($out[0]);
            for ($i = 0; $i < $outCount; $i++) {
                $out[2][$i] = preg_replace("(\"|')", "", $out[2][$i]);
                $sHREF[] = array($out[1][$i], $out[2][$i]);
            }
        }

        if (in_array($p_tag, $aTAGS)) {
            //tag already opened
            return false;
        }

        if (in_array("</$p_tag>", $p_array)) {
            $aTAGS[] = $p_tag;
            $aHREF[] = $sHREF;
            return true;
        }

        return false;
    }

    /**
     * returns true if $p_tag is a "<close tag>"
     * @param    $p_tag - tag string
     * $p_array - tag array;
     * @return true/false
     */
    /**
     * Returns true if $p_tag is a "<close tag>"
     *
     * @access    protected
     * @param string $p_tag - tag name
     * @return    boolean
     */
    protected function CloseTag($p_tag)
    {
        $aTAGS = &$this->aTAGS;
        $aHREF = &$this->aHREF;
        $maxElem = &$this->iTagMaxElem;

        if (!preg_match('~^</([a-zA-Z1-9]{1,' . $maxElem . '})>$~', $p_tag, $reg)) {
            return false;
        }

        $p_tag = $reg[1];

        if (in_array("$p_tag", $aTAGS)) {
            array_pop($aTAGS);
            array_pop($aHREF);
            return true;
        }

        return false;
    }// CloseTag

    /**
     * Expands the paramteres that are kept in Href field
     *
     * @access    protected
     * @param    array $pResult
     */
    protected function expand_parameters($pResult)
    {
        $aTmp = $pResult['params'];
        if ($aTmp <> '') {
            $aTmpCount = count($aTmp);
            for ($i = 0; $i < $aTmpCount; $i++) {
                $pResult[$aTmp[$i][0]] = $aTmp[$i][1];
            }
        }

        unset($pResult['params']);

        return $pResult;
    }

    /**
     * Optimizes the result of the tag result array
     * In the result array there can be strings that are consecutive and have the same tag, they
     * are concatenated.
     *
     * @access    protected
     * @param    array $result - the array that has to be optimized
     * @return    array - optimized result
     */
    protected function optimize_tags($result)
    {
        if (count($result) == 0) {
            return $result;
        }

        $res_result = array();
        $current = $result[0];
        $i = 1;

        while ($i < count($result)) {

            //if they have the same tag then we concatenate them
            if (($current['tag'] == $result[$i]['tag']) && ($current['params'] == $result[$i]['params'])) {
                $current['text'] .= $result[$i]['text'];
            } else {
                $current = $this->expand_parameters($current);
                $res_result[] = $current;
                $current = $result[$i];
            }

            $i++;
        }

        $current = $this->expand_parameters($current);
        $res_result[] = $current;

        return $res_result;
    }

    /**
     * Parses a string and returnes the result
     * @param    $p_str - string
     * @return array (
     * array (string1, tag1),
     * array (string2, tag2)
     * )
     */
    /**
     * Parses a string and returnes an array of TAG - SRTING correspondent array
     * The result has the following structure:
     *        array(
     *            array (string1, tag1),
     *            array (string2, tag2),
     *            ... etc
     *        )
     *
     * @access    public
     * @param    string $p_str - the Input String
     * @return    array - the result array
     */
    public function get_tags($p_str)
    {
        $aTAGS = &$this->aTAGS;
        $aHREF = &$this->aHREF;
        $aTAGS = array();
        $result = array();

        $reg = preg_split('/(<.*>)/U', $p_str, -1, PREG_SPLIT_DELIM_CAPTURE);

        $sTAG = "";
        $sHREF = "";

        foreach ($reg as $val) {
            if ($val == "") {
                continue;
            }

            if ($this->OpenTag($val, $reg)) {
                $sTAG = (($temp = end($aTAGS)) != NULL) ? $temp : "";
                $sHREF = (($temp = end($aHREF)) != NULL) ? $temp : "";
            } elseif ($this->CloseTag($val)) {
                $sTAG = (($temp = end($aTAGS)) != NULL) ? $temp : "";
                $sHREF = (($temp = end($aHREF)) != NULL) ? $temp : "";
            } else {
                if ($val != "") {
                    $result[] = array('text' => $val, 'tag' => $sTAG, 'params' => $sHREF);
                }
            }
        }

        return $this->optimize_tags($result);
    }
}