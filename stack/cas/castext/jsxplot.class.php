<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * If blocks hide their content if the value of their test-attribute is not "true".
 *
 * @copyright  2013 Aalto University
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../casstring.class.php');
require_once(__DIR__ . '/../../../locallib.php');
require_once(__DIR__ . '/../../utils.class.php');

class stack_cas_castext_jsxplot extends stack_cas_castext_block {

    /**
     * remembers the number for this excecution / set of questions / quiz, graph identifiers must be unique.
     */
    private static $graphcountter = 0;

    /**
     * remembers the number for this graph, graph identifiers must be unique.
     */
    private $number;

    /**
     * The default colours used for plotting. Basically, the gnuplot defaults.
     */
    static $colours = array('#0072bd','#d95319','#edb120','#7e2f8e','#77ac30','#4dbeee','#a2142f');

    /**
     * The default point-symbols for scatter.
     */
    static $points = array('o','[]','x','+','^','v','>','<','<>');

    /**
     * Things to draw.
     */
    private $drawables = array();

    public function extract_attributes(&$tobeevaluatedcassession, $conditionstack = null) {
        $xrange = '[-5,5]';
        $yrange = '[auto,auto]';
        $legend = '[]';
        $options = '[]';
        // We do this with this switch case as we actually care about the order of parameters, you can have functions before or
        // after scatter plots in the legend and it depends on the declaration order.
        $index = 0;

        $this->drawables = array();

        foreach ($this->get_node()->get_parameters() as $key => $value) {
            switch($key) {
                case 'xrange':
                    $xrange = $value;
                    break;
                case 'yrange':
                    $yrange = $value;
                    break;
                case 'graphsettings':
                    $graphsettings = $value;
                    break;
                case 'legend':
                    $legend = $value;
                    break;
                case 'options':
                    $options = $value;
                    break;
                case 'functions':
                case 'scatters':
                    $asarray = stack_utils::list_to_array($value, false);

                    foreach ($asarray as $toplevel) {
                        $data = $toplevel;

                        $drawable = false;
                        if ($key == 'functions') {
                            $drawable = new stack_cas_castext_jsxplot_draw_function();
                        } else if ($key == 'scatters') {
                            $drawable = new stack_cas_castext_jsxplot_draw_scatter();
                        }
                        $drawable->index = $index;
                        $drawable->data = $data;
                        $this->drawables[] = $drawable;

                        $index = $index + 1;
                    }
                    break;
            }
        }
        if ($legend !== false) {
            $strings = stack_utils::all_substring_strings($legend);
            foreach ($strings as $skey => $string) {
                $legend = str_replace('"'.$string.'"', '[STR:'.$skey.']', $legend);
            }
            $asarray = stack_utils::list_to_array($legend, false);
            foreach ($asarray as $key => $value) {
                if ($value !== false && $value !== 'false') {
                    foreach ($strings as $skey => $svalue) {
                        $value = str_replace('[STR:'.$skey.']', '"'.$svalue.'"', $value);
                    }

                    $stripquotes = substr($value, 1, strlen($value) - 2);
                    $this->drawables[$key]->legend = $stripquotes;
                }
            }
        }

        if ($options !== false) {
            $asarray = stack_utils::list_to_array($options, false);
            foreach ($asarray as $key => $value) {
                if ($value !== false && $value !== 'false') {
                    // Quotes here actually {...}.
                    $stripquotes = substr($value, 1, strlen($value) - 2);
                    $this->drawables[$key]->options = $stripquotes;
                }
            }
        }

        $sessionkeys = $tobeevaluatedcassession->get_all_keys();
        $this->number = stack_cas_castext_jsxplot::$graphcountter;
        do { // ... make sure names are not already in use.
            $xkey = 'plotjsxrange'.$this->number;
            $ykey = 'plotjsyrange'.$this->number;
            $nkey = 'plotjsnumer'.$this->number;
            $this->number++;
        } while (in_array($xkey, $sessionkeys) || in_array($ykey, $sessionkeys) || in_array($nkey, $sessionkeys));
        stack_cas_castext_jsxplot::$graphcountter = $this->number;
        $this->number--;

        $xkey = 'plotjsxrange'.$this->number;
        $ykey = 'plotjsyrange'.$this->number;
        $nkey = 'plotjsnumer'.$this->number;

        // Turn numer on before anything gets evaluated
        $cs0 = new stack_cas_casstring('numer', $conditionstack);
        $cs0->get_valid($this->security, $this->syntax, $this->insertstars);
        $cs0->set_key($nkey, true);

        $cs1 = new stack_cas_casstring('true', $conditionstack);
        $cs1->get_valid($this->security, $this->syntax, $this->insertstars);
        $cs1->set_key('numer', true);

        $tobeevaluatedcassession->add_vars(array($cs0, $cs1));

        foreach ($this->drawables as $drawable) {
            $drawable->construct_evaluation_code($tobeevaluatedcassession, $conditionstack, $this->security, $this->syntax, $this->insertstars);
        }

        $cs1 = new stack_cas_casstring($xrange, $conditionstack);
        $cs1->get_valid($this->security, $this->syntax, $this->insertstars);
        $cs1->set_key($xkey, true);

        $yasarray = stack_utils::list_to_array($yrange, false);
        $xasarray = stack_utils::list_to_array($xrange, false);
        if (trim($yasarray[0]) == 'auto') {
            $mins = array();
            foreach ($this->drawables as $drawable) {
                $mins[] = $drawable->get_min($xasarray[0], $xasarray[1]);
            }
            $yasarray[0] = 'min(' . implode(',',$mins) . ')';
        }
        if (trim($yasarray[1]) == 'auto') {
            $maxs = array();
            foreach ($this->drawables as $drawable) {
                $maxs[] = $drawable->get_max($xasarray[0], $xasarray[1]);
            }
            $yasarray[1] = 'max(' . implode(',',$maxs) . ')';
        }

        // Pointles implode but what if the yrange had more elements?
        $cs2 = new stack_cas_casstring('[' . implode(',',$yasarray) . ']', $conditionstack);
        $cs2->get_valid($this->security, $this->syntax, $this->insertstars);
        $cs2->set_key($ykey, true);

        $cs3 = new stack_cas_casstring($nkey, $conditionstack);
        $cs3->get_valid($this->security, $this->syntax, $this->insertstars);
        $cs3->set_key('numer', true);


        $tobeevaluatedcassession->add_vars(array($cs1, $cs2, $cs3));
    }

    public function content_evaluation_context($conditionstack = array()) {
        return $conditionstack;
    }

    public function process_content($evaluatedcassession, $conditionstack = null) {
        $xrange = stack_utils::list_to_array($evaluatedcassession->get_value_key('plotjsxrange'.$this->number),false);
        $yrange = stack_utils::list_to_array($evaluatedcassession->get_value_key('plotjsyrange'.$this->number),false);

        $legend = trim($this->get_node()->get_parameter('legend','[]'));
        $options = trim($this->get_node()->get_parameter('graphsettings','axis:true'));

        if (strpos($options,'boundingbox')===FALSE) {
            $bb = 'boundingbox:['.$xrange[0].','.$yrange[1].','.$xrange[1].','.$yrange[0].']';
            if ($options == '') {
                $options = $bb;
            } else {
                $options .= ','.$bb;
            }
        }

        $width = $this->get_node()->get_parameter('width', '40em');
        $height = $this->get_node()->get_parameter('height', '30em');

        $content = '<jsxgraph width="'.$width.';blaahx:0" height="'.$height.';blaahy:0" box="stack_jsxplot_'.$this->number.'">';
        $content .= '(function() {';
        // This would be nice but can't due to Moodle...
        //$content .= 'JXG.Options.text.useMathJax = true;';
        $content .= 'var brd = JXG.JSXGraph.initBoard("stack_jsxplot_'.$this->number.'", {'.$options.'});';

        foreach ($this->drawables as $drawable) {
            $content .= $drawable->plot($evaluatedcassession);
        }

        // TODO: Legend if used should be here.
        // So we do this.
        $content .= 'Y.use("moodle-filter_mathjaxloader-loader",function() {M.filter_mathjaxloader.typeset();});';
        $content .= '})();';
        $content .= '</jsxgraph>';

        $this->get_node()->convert_to_text($content);

        return (strpos($content,'{@') !== FALSE && strpos($content,'@}') !== FALSE) || (strpos($content,'{#') !== FALSE && strpos($content,'#}') !== FALSE);
    }


    public function validate_extract_attributes() {
        $condition = $this->get_node()->get_parameter('test', 'false');
        $r = array(new stack_cas_casstring($condition));
        return $r;
    }

    public function validate(&$errors='') {
        $valid = parent::validate($errors);

        $functions = trim($this->get_node()->get_parameter('functions','[]'));
        $scatters = trim($this->get_node()->get_parameter('scatters','[]'));
        $legend = trim($this->get_node()->get_parameter('legend','[]'));
        $options = trim($this->get_node()->get_parameter('options','[]'));
        $xrange = trim($this->get_node()->get_parameter('xrange','[-5,5]'));
        $yrange = trim($this->get_node()->get_parameter('yrange','[auto,auto]'));
        $graphsettings = trim($this->get_node()->get_parameter('graphsettings','axis:true'));

        // TODO check that there is something to plot and do something with those others...

        return $valid;
    }
}

/**
 * Common details and features of plottable things.
 */
abstract class stack_cas_castext_jsxplot_draw {
    public $legend = 'false';
    public $options = '';
    public $data = false;

    // Index of this thing among things.
    private $index = -1;

    /**
     * Takes the data be it a JessieCode presentation of a function or a list of points and generate the JavaScript to plot it
     * using 'brd' as the JSXGraph board.
     * @param session with evaluated things
     * @return string JavaScript
     */
    abstract  function plot($evaluatedsession);

    /**
     * Functions returning maxima statements defining the maximum and minimumn y-value of this object between specific x-values.
     * Used with the auto y-scale feature. Aim for short maxima code and reuse the data variables to pass large sets of data.
     * Must return numeric values with , numer. The range given is in arbitrary maxima expressions giving numbers.
     */
    abstract  function get_max($xmin = '-5', $xmax = '5');
    abstract  function get_min($xmin = '-5', $xmax = '5');

    /**
     * Adds casstrings to a session to generate whatever needs to be generated for this drawing.
     */
    abstract  function construct_evaluation_code(&$tobeevaluatedcasession, $conditionstack, $security, $syntax, $insertstars);
}

class stack_cas_castext_jsxplot_draw_function extends stack_cas_castext_jsxplot_draw {
    /**
     * The number for the variables storing the Jessie Code representation of this function and options and legend.
     */
    private $conversionnumber = false;

     function plot($evaluatedsession) {
        $jessie = $evaluatedsession->get_value_key('plotjcfunction'.$this->conversionnumber);
        $jessie = substr($jessie, 1, strlen($jessie) - 2);

        // Ensure a colour has been defined.
        if ($this->options == '') {
            $this->options = 'strokeColor:\'' .
                    stack_cas_castext_jsxplot::$colours[$this->index % count(stack_cas_castext_jsxplot::$colours)] . '\'';
        } else if (strpos($this->options,'strokeColor')===FALSE) {
            $this->options .= ',strokeColor:\'' .
                    stack_cas_castext_jsxplot::$colours[$this->index % count(stack_cas_castext_jsxplot::$colours)] . '\'';
        }

        $js = "brd.create('functiongraph',brd.jc.snippet('" . $jessie . "', true, 'x', true),{" . $this->options . "});";

        return $js;
    }

     function get_min($xmin = '-5', $xmax = '5') {
        $scale = "50.0/(($xmax) - ($xmin))";
        // One ugly way to handle most functions... may require future expansion to handle 'und'. And could sample more or do
        // symbolic Bolzano things, but as we know nothing about the function...
        return "lmin(delete(-infinity,delete(infinity,makelist(ev(limit(" . $this->data . ",x,t)),t,makelist(x/($scale),x,(($xmin)*$scale),(($xmax)*($scale)))))))";
    }

     function get_max($xmin = '-5', $xmax = '5') {
        $scale = "50.0/(($xmax) - ($xmin))";
        // One ugly way to handle most functions... may require future expansion to handle 'und'.
        return "lmax(delete(-infinity,delete(infinity,makelist(ev(limit(" . $this->data . ",x,t)),t,makelist(x/($scale),x,(($xmin)*$scale),(($xmax)*($scale)))))))";
    }

     function construct_evaluation_code(&$tobeevaluatedcasession, $conditionstack, $security, $syntax, $insertstars) {
        if ($this->conversionnumber === false) {
            $sessionkeys = $tobeevaluatedcasession->get_all_keys();
            $i = 0;
            do { // ... make sure names are not already in use.
                $fkey = 'plotjcfunction'.$i;
                $i++;
            } while (in_array($fkey, $sessionkeys));
            $this->conversionnumber = $i - 1;
        }
        $fkey = 'plotjcfunction'.$this->conversionnumber;

        $cs0 = new stack_cas_casstring('stack_maxima_to_jessie_code(' . $this->data . ')', $conditionstack);
        $cs0->get_valid($security, $syntax, $insertstars);
        $cs0->set_key($fkey, true);

        $tobeevaluatedcasession->add_vars(array($cs0));
    }
}


class stack_cas_castext_jsxplot_draw_scatter extends stack_cas_castext_jsxplot_draw {
    /**
     * The number for the variables storing the Jessie Code representation of this function and options and legend.
     */
    private $conversionnumber = false;

     function plot($evaluatedsession) {
        // Ensure a colour has been defined.
        if ($this->options == '') {
            $this->options = 'strokeColor:\'' .
                    stack_cas_castext_jsxplot::$colours[$this->index % count(stack_cas_castext_jsxplot::$colours)] . '\'';
        } else if (strpos($this->options,'strokeColor')===FALSE) {
            $this->options .= ',strokeColor:\'' .
                    stack_cas_castext_jsxplot::$colours[$this->index % count(stack_cas_castext_jsxplot::$colours)] . '\'';
        }
        // Ensure that scatter plots have point-styles.
        if (strpos($this->options,'face')===FALSE) {
            $this->options .= ',face:\'' . stack_cas_castext_jsxplot::$points[$this->index % count(stack_cas_castext_jsxplot::$points)] . '\'';
        }
        // By default we will ensure things plotted stay fixed, except legends.
        if (strpos($this->options,'fixed')===FALSE) {
            $this->options .= ',fixed:true';
        }

        // By default we have no names for the points
        if (strpos($this->options,'name')===FALSE) {
            $this->options .= ',name:""';
        }

        $js = "var table = " . $evaluatedsession->get_value_key('plotjxdata'.$this->conversionnumber) . ";for(i=0;i&lt;table.length;i++) brd.create('point',[table[i][0],table[i][1]],{" . $this->options . "});";
        return $js;
    }

    function get_min($xmin = '-5', $xmax = '5') {
        return "lmin(maplist(lambda([x],x[2]),sublist(plotjxdata".$this->conversionnumber.",lambda([x],is(x[1]>=($xmin)) and is(x[1]<=($xmax))))))";
    }

    function get_max($xmin = '-5', $xmax = '5') {
        return "lmax(maplist(lambda([x],x[2]),sublist(plotjxdata".$this->conversionnumber.",lambda([x],is(x[1]>=($xmin)) and is(x[1]<=($xmax))))))";
    }

    function construct_evaluation_code(&$tobeevaluatedcasession, $conditionstack, $security, $syntax, $insertstars) {
        if ($this->conversionnumber === false) {
            $sessionkeys = $tobeevaluatedcasession->get_all_keys();
            $i = 0;
            do { // ... make sure names are not already in use.
                $dkey = 'plotjxdata'.$i;
                $i++;
            } while (in_array($dkey, $sessionkeys));
            $this->conversionnumber = $i - 1;
        }
        $dkey = 'plotjxdata'.$this->conversionnumber;

        $cs0 = new stack_cas_casstring($this->data, $conditionstack);
        $cs0->get_valid($security, $syntax, $insertstars);
        $cs0->set_key($dkey, true);

        $tobeevaluatedcasession->add_vars(array($cs0));
    }
}
