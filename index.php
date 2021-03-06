<?php
/**
 * Pengakar: Indonesian stemmer
 * Copyright (C) 2012 Ivan Lanin <ivan at lanin dot org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
error_reporting(E_ALL & ~E_NOTICE);

// Parameters
$pengakar = new pengakar;
$url = $_GET['url'] ? $_GET['url'] : $_POST['url'];
if ($url)
    $q = $pengakar->get_content($url);
else
    $q = stripslashes($_GET['q'] ? $_GET['q'] : $_POST['q']); // Suhosin?
// Process API
if (isset($_GET['api']))
    die($pengakar->get_api($q));
// Process HTML
if ($q)
{
    $result .= '<h3>Daftar kata</h3>';
    $result .= $pengakar->get_html($q);
}
// Form
$form .= '<form action="./" method="post" style="margin-bottom: 20px;">';
$form .= 'URL:<br /><input type="text" id="url" name="url" value="' . $url . '" />';
$form .= 'Teks:<br /><textarea id="q" name="q">' . $q . '</textarea>';
$form .= '<br />';
$form .= '<input type="submit" value="Proses" />';
$form .= '</form>';
// HTML
$ret = $form . $result;
$info = 'Seret dan lepaskan tautan ini ke bilah markah peramban untuk membuat ' .
    'bookmarklet yang dapat dipakai untuk menjalankan pengakar untuk ' .
    'menganalisis isi situs yang sedang Anda kunjungi.';
$bookmarklet = 'javascript:(function(){window.open(\'' .
    'http://' . $_SERVER['SERVER_NAME'] . '/pengakar/' .
    '?url=\'+encodeURIComponent(location.href));})();';
?>
<html>
<head>
<title>Pengakar</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>
a { text-decoration: none; }
hr { margin-top: 20px; height: 1px; border-width:0; background: #999; }
.instance { color:#666; font-style:italic; font-size:80%; }
.wclass { color:#666; font-style:italic; font-size:80%; }
.notfound { color:#f00; }
#url { width:100%; }
#q { width:100%; height:200px; }
</style>
<body>
<h2><a href="./">Pengakar</a></h2>

<p><a title="<?php echo($info); ?>" href="<?php echo($bookmarklet); ?>">Pengakar</a>
(<em><a href="http://en.wikipedia.org/wiki/Stemming">stemmer</a></em>)
adalah program pencari akar kata bahasa Indonesia. Program ini dibuat dengan
menyempurnakan beberapa algoritme yang diperoleh dari berbagai sumber, terutama
artikel "<a href="http://dl.acm.org/citation.cfm?id=1082195">Stemming Indonesian</a>"
(Asian, 2005). Tentu saja program ini masih terus disempurnakan dan, karena itu,
mohon bantuan untuk melaporkan kesalahan kepada
<a href="http://twitter.com/ivanlanin">@ivanlanin</a>. Terima kasih.</p>

<p>Masukkan alamat laman web pada kotak "URL" atau teks pada kotak "teks".
Klik "Proses" dan pengakar akan berupaya mencari akar kata tersebut.
Warna <span style="color:#f00">merah</span> berarti kata tersebut tidak ditemukan
di dalam leksikon dan diletakkan paling atas dalam daftar.
Daftar kata diurutkan berdasarkan jumlah kemunculan.</p>
<?php echo($ret); ?>

<hr />
<a href="./README.TXT">README</a> |
<a href="./?api=1">API</a> |
<a href="https://github.com/ivanlanin/pengakar">Kode</a>
(<a href="http://www.gnu.org/licenses/gpl.html">GPL</a>) |
<a href="./kamus.txt">Leksikon</a>
(<a href="http://creativecommons.org/licenses/by-nc/3.0/">BY-NC</a>)
</body>
</html>
<?php

/**
 * Main class
 */
class pengakar
{
    var $dict;
    var $rules;
    var $options;

    /**
     *
     */
    function __construct()
    {
        // Read dictionary and create associative array
        $dict = file_get_contents('./kamus.txt');
        $tmp = explode("\n", $dict);
        foreach ($tmp as $entry)
        {
            $attrib = explode("\t", strtolower($entry)); // 0: class; 1: lemma
            $key = str_replace(' ', '', $attrib[1]); // remove space
            $this->dict[$key] = array('class' => $attrib[0], 'lemma' => $attrib[1]);
        }
        // Options
        $this->options = array(
            'SORT_INSTANCE' => false, // sort by number of instances
            'NO_NO_MATCH'   => false, // hide no match entry
            'NO_DIGIT_ONLY' => true, // hide digit only
            'STRICT_CONFIX' => false, // use strict disallowed_confixes rules
        );
        // Define rules
        $VOWEL = 'a|i|u|e|o'; // vowels
        $CONSONANT = 'b|c|d|f|g|h|j|k|l|m|n|p|q|r|s|t|v|w|x|y|z'; // consonants
        $ANY = $VOWEL . '|' . $CONSONANT; // any characters
        $this->rules = array(
            'affixes' => array(
                array(1, array('kah', 'lah', 'tah', 'pun')),
                array(1, array('mu', 'ku', 'nya')),
                array(0, array('ku', 'kau')),
                array(1, array('i', 'kan', 'an')),
            ),
            'prefixes' => array(
                array(0, "(di|ke|se)({$ANY})(.+)", ""), // 0
                array(0, "(ber|ter)({$ANY})(.+)", ""), // 1, 6 normal
                array(0, "(be|te)(r)({$VOWEL})(.+)", ""), // 1, 6 be-rambut
                array(0, "(be|te)({$CONSONANT})({$ANY}?)(er)(.+)", ""), // 3, 7 te-bersit, te-percaya
                array(0, "(bel|pel)(ajar|unjur)", ""), // ajar, unjur
                array(0, "(me|pe)(l|m|n|r|w|y)(.+)", ""), // 10, 20: merawat, pemain
                array(0, "(mem|pem)(b|f|v)(.+)", ""), // 11 23: membuat, pembuat
                array(0, "(men|pen)(c|d|j|z)(.+)", ""), // 14 27: mencabut, pencabut
                array(0, "(meng|peng)(g|h|q|x)(.+)", ""), // 16 29: menggiring, penghasut
                array(0, "(meng|peng)({$VOWEL})(.+)", ""), // 17 30 meng-anjurkan, peng-anjur
                array(0, "(mem|pem)({$VOWEL})(.+)", "p"), // 13 26: memerkosa, pemerkosa
                array(0, "(men|pen)({$VOWEL})(.+)", "t"), // 15 28 menutup, penutup
                array(0, "(meng|peng)({$VOWEL})(.+)", "k"), // 17 30 mengalikan, pengali
                array(0, "(meny|peny)({$VOWEL})(.+)", "s"), // 18 31 menyucikan, penyucian
                array(0, "(mem)(p)({$CONSONANT})(.+)", ""), // memproklamasikan
                array(0, "(pem)({$CONSONANT})(.+)", "p"), // pemrogram
                array(0, "(men|pen)(t)({$CONSONANT})(.+)", ""), // mentransmisikan pentransmisian
                array(0, "(meng|peng)(k)({$CONSONANT})(.+)", ""), // mengkristalkan pengkristalan
                array(0, "(men|pen)(s)({$CONSONANT})(.+)", ""), // mensyaratkan pensyaratan
                array(0, "(menge|penge)({$CONSONANT})(.+)", ""), // swarabakti: mengepel
                array(0, "(mempe)(r)({$VOWEL})(.+)", ""), // 21
                array(0, "(memper)({$ANY})(.+)", ""), // 21
                array(0, "(pe)({$ANY})(.+)", ""), // 20
                array(0, "(per)({$ANY})(.+)", ""), // 21
                array(0, "(pel)({$CONSONANT})(.+)", ""), // 32 pelbagai, other?
                array(0, "(mem)(punya)", ""), // Exception: mempunya
                array(0, "(pen)(yair)", "s"), // Exception: penyair > syair
            ),
            'disallowed_confixes' => array(
                array('ber-', '-i'),
                array('ke-', '-i'),
                array('pe-', '-kan'),
                array('di-', '-an'),
                array('meng-', '-an'),
                array('ter-', '-an'),
                array('ku-', '-an'),
            ),
            'allomorphs' => array(
                'be' => array('be-', 'ber-', 'bel-'),
                'te' => array('te-', 'ter-', 'tel-'),
                'pe' => array('pe-', 'per-', 'pel-', 'pen-', 'pem-', 'peng-', 'peny-', 'penge-'),
                'me' => array('me-', 'men-', 'mem-', 'meng-', 'meny-', 'menge-'),
            ),
        );

    }

    function get_content($url)
    {
        // Curl
        $agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en; rv:1.9.0.4) Gecko/2009011913 Firefox/3.0.6";
        $domain = 'http://' . parse_url($url, PHP_URL_HOST);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_REFERER, $domain); // pseudo referer
        curl_setopt($curl, CURLOPT_USERAGENT, $agent); // pseudo agent
        $html = curl_exec($curl);
        curl_close($curl);
        // Process HTML
        $ret = $html;
        $ret = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', "", $ret); // remove script & style
        $ret = preg_replace('/<(br|p)[^>]*>/i', "\n", $ret); // new line for br & p
        $ret = trim(strip_tags($ret)); // strip tags
        $ret = preg_replace('/^\s*/m', '', $ret); // trim left
        $ret = preg_replace('/\s*$/m', '', $ret); // trim right
        $ret = preg_replace('/\n+/', "\n\n", $ret); // two new line: readability
        return($ret);
    }

    /**
     * Get API result
     */
    function get_api($query)
    {
        $words = $this->stem($query);
        if ($query != '')
            return(json_encode($words));
        return('API Pengakar<br /><br />' .
            'Sintaks:<br />' .
            '* <a href="./?api=1&q=pengakar">?api=1&q=...</a><br />' .
            '* <a href="./?api=1&url=http://ivan.lanin.org/pengakar/">?api=1&url=...</a><br /><br />' .
            'Hasil:<br />' .
            'lemma => { <br />' .
            '&nbsp;&nbsp;count,<br />' .
            '&nbsp;&nbsp;roots => { <br />' .
            '&nbsp;&nbsp;&nbsp;&nbsp;root => lemma, affixes {}, suffixes {}, prefixes {} <br />' .
            '&nbsp;&nbsp;} <br />' .
            '}' .
            '');
    }

    /**
     * Get HTML result
     */
    function get_html($query)
    {
        $words = $this->stem($query);
        $url_template = 'http://kateglo.bahtera.org/?mod=dict&action=view&phrase=%1$s';

        // Render display
        $word_count = count($words);
        foreach ($words as $key => $word)
        {
            $roots = $word['roots'];
            $root_count = count($roots);
            //if ($root_count <= 1) continue; // display disambig only
            if ($word['count'] > 1)
                $instances = ' <span class="instance">x' . $word['count'] . '</span>';
            else
                $instances = '';
            if ($root_count == 0) // no match
                $lost .= sprintf('<li><span class="notfound">%s</span>%s</li>',
                    $key, $instances);
            else
            {
                $i = 0; unset($components);
                foreach ($roots as $lemma => $attrib)
                {
                    $i++;
                    $affixes = $attrib['affixes'];
                    $url = sprintf($url_template, $attrib['lemma']);
                    $lemma_url = sprintf('<a href="%s" target="kateglo">%s</a>', $url, $attrib['lemma']);
                    $components .= $components ? '; ' : '';
                    if ($key == $lemma && $root_count == 1) // is baseword
                        $components .= $lemma_url . $instances . $class;
                    else
                    {
                        // Multiroot
                        if ($root_count > 1 && $i == 1)
                            $components .= $key . $instances . ': ';
                        if ($root_count > 1)
                            $components .= "({$i}) ";
                        // Prefix, lemma, & suffix
                        if (is_array($attrib['prefixes']))
                            $components .= implode('', $attrib['prefixes']);
                        $components .= $lemma_url;
                        if (is_array($attrib['suffixes']))
                            $components .= implode('', $attrib['suffixes']);
                        // Single root
                        if ($root_count == 1)
                            $components .= $instances;
                    }
                }
                $found .= sprintf('<li>%s</li>', $components);
            }
        }
        // Render display
        if ($word_count >= 10)
            $ret .= '<div style="-webkit-column-count: 3; -moz-column-count: 3;">';
        $ret .= '<ol style="margin:0;">';
        $ret .= $lost;
        $ret .= $found;
        $ret .= '</ol>';
        if ($word_count >= 10)
            $ret .= '</div>';
        return($ret);
    }

    /**
     * Tokenization
     */
    function stem($query)
    {
        $words = array();
        $raw = preg_split('/[^a-zA-Z0-9\-]/', $query, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($raw as $r)
        {
            // Remove all digit "word" if necessary
            if ($this->options['NO_DIGIT_ONLY'] && preg_match('/^\d+$/', $r))
                continue;
            $key = strtolower($r);
            $words[$key]['count']++;
        }
        foreach ($words as $key => $word)
        {
            $words[$key]['roots'] = $this->stem_word($key);
            // If NO_NO_MATCH, remove words that has no root
            if (count($words[$key]['roots']) == 0 && $this->options['NO_NO_MATCH'])
            {
                unset($words[$key]);
                continue;
            }
            $instances[$key] = $word['count'];
        }
        $word_count = count($words);
        if ($this->options['SORT_INSTANCE'])
        {
            $keys = array_keys($words);
            array_multisort($instances, SORT_DESC, $keys, SORT_ASC, $words);
        }
        else
            ksort($words);
        return($words);
    }

    /**
     * Stem individual word
     */
    function stem_word($word)
    {
        // Preprocess: Create empty affix if original word is in lexicon
        $word = trim($word);
        $roots = array($word => '');
        if (array_key_exists($word, $this->dict))
            $roots[$word]['affixes'] = array();
        // Has dash? Try to also find root for each element
        if (strpos($word, '-'))
        {
            $dash_parts = explode('-', $word);
            foreach ($dash_parts as $dash_part)
                $roots[$dash_part]['affixes'] = array();
        }

        // Process: Find suffixes, pronoun prefix, and other prefix (3 times, Asian)
        foreach ($this->rules['affixes'] as $group)
        {
            $is_suffix = $group[0];
            $affixes = $group[1];
            foreach ($affixes as $affix)
            {
                $pattern = $is_suffix ? "(.+)({$affix})" : "({$affix})(.+)";
                $this->add_root($roots, array($is_suffix, $pattern, ''));
            }
        }
        for ($i = 0; $i < 3; $i++)
            foreach ($this->rules['prefixes'] as $rule)
                $this->add_root($roots, $rule);

        // Postprocess 1: Select valid affixes
        foreach ($roots as $lemma => $attrib)
        {
            // Not in dictionary? Unset and exit
            if (!array_key_exists($lemma, $this->dict))
            {
                unset($roots[$lemma]);
                continue;
            }
            // Escape if we don't have to check valid confix pairs
            if (!$this->options['STRICT_CONFIX'])
                continue;
            // Check confix pairs
            $affixes = $attrib['affixes'];
            foreach ($this->rules['disallowed_confixes'] as $pair)
            {
                $prefix = $pair[0];
                $suffix = $pair[1];
                $prefix_key = substr($prefix, 0, 2);
                if (array_key_exists($prefix_key, $this->rules['allomorphs']))
                {
                    foreach ($this->rules['allomorphs'][$prefix_key] as $allomorf)
                        if (in_array($allomorf, $affixes) && in_array($suffix, $affixes))
                            unset($roots[$lemma]);
                }
                else
                    if (in_array($prefix, $affixes) && in_array($suffix, $affixes))
                        unset($roots[$lemma]);
            }
        }

        // Postprocess 2: Handle suffixes and prefixes
        foreach ($roots as $lemma => $attrib)
        {
            $affixes = $attrib['affixes'];
            $attrib['lemma'] = $this->dict[$lemma]['lemma'];
            $attrib['class'] = $this->dict[$lemma]['class'];
            // Divide affixes into suffixes and prefixes
            foreach ($attrib['affixes'] as $affix)
            {
                $type = (substr($affix, 0, 1) == '-') ? 'suffixes' : 'prefixes';
                $attrib[$type][] = $affix;
            }
            // Reverse suffix order
            if (is_array($attrib['suffixes']))
                krsort($attrib['suffixes']);
            $roots[$lemma] = $attrib;
        }
        return($roots);
    }

    /**
     * Greedy algorithm: add every possible branch
     */
    function add_root(&$roots, $rule)
    {
        $is_suffix = $rule[0];
        $pattern = '/^' . $rule[1] . '$/i';
        $variant = $rule[2];
        foreach ($roots as $lemma => $attrib)
        {
            preg_match($pattern, $lemma, $matches);
            if (count($matches) > 0)
            {
                unset($new_lemma); unset($new_affix);
                $affix_index = $is_suffix ? 2 : 1;

                // Lemma
                for ($i = 1; $i < count($matches); $i++)
                    if ($i != $affix_index)
                        $new_lemma .= $matches[$i];
                if ($variant)
                    $new_lemma = $variant . $new_lemma;

                // Affix, add - before (suffix), after (prefix)
                $new_affix .= $is_suffix ? '-' : '';
                $new_affix .= $matches[$affix_index];
                $new_affix .= $is_suffix ? '' : '-';
                $new_affix = array($new_affix); // make array
                if (is_array($attrib['affixes'])) // merge
                    $new_affix = array_merge($attrib['affixes'], $new_affix);

                // Push
                $roots[$new_lemma] = array('affixes' => $new_affix);
            }
        }
    }
}