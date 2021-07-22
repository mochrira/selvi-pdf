<?php 

namespace Selvi;

use TCPDF;

class Pdf extends TCPDF {

    function __construct() {}

    private $init = false;
    private $pageArgs = ['orientation' => 'P', 'units' => 'in', 'size' => 'A4'];

    function pageStart($args = []) {
        $this->pageArgs = array_merge($this->pageArgs, $args);
        if(!$this->init) {
            parent::__construct($this->pageArgs['orientation'], $this->pageArgs['units'], $this->pageArgs['size']);
            $this->init = true;
        }

        if($this->headerCallback !== null) {
            $this->SetPrintHeader(true);
        }
        if(isset($this->pageArgs['margins'])) {
            $this->SetMargins(...$this->pageArgs['margins']);
        }

        $this->setAutoPageBreak(false);
        $this->AddPage($this->pageArgs['orientation'], $this->pageArgs['size']);
    }

    function pageEnd() {
        $this->endPage();
    }

    private $headerCallback;
    private $onHeader = false;

    function pageHeader($callback) {
        $this->headerCallback = $callback;
    }

    public function Header() {
        $this->onHeader = true;
        call_user_func($this->headerCallback);
        $this->onHeader = false;
    }

    private $cols = [];
    private $simulate = true;

    function percentWidth($percent) {
        $margins = $this->getMargins();
        $width = $this->getPageWidth();
        $innerWidth = $width - $margins['left'] - $margins['right'];
        return (float)$percent / 100 * $innerWidth;
    }

    function rowStart() {
        // start simulation
        $this->simulate = true;
        $this->cols = [];
        $this->startTransaction();
    }

    function column($txt, $options, $break = false) {
        // merge options
        $options = array_merge([
            'height' => 0,
            'align' => 'L',
            'valign' => 'M',
            'multiline' => false,
            'border' => 1
        ], $options);

        // decide multiline or not
        if($options['multiline'] == true) {
            $this->MultiCell(
                (strpos($options['width'], '%') === false ? $options['width'] : $this->percentWidth($options['width'])), // width
                $options['height'], // height
                $txt, // content
                $options['border'], // border
                $options['align'], 
                0, // fill
                $break ? 1 : 0 // break
            );
        } else {
            $this->Cell(
                (strpos($options['width'], '%') === false ? $options['width'] : $this->percentWidth($options['width'])), // width
                $options['height'], // height
                $txt, // content
                $options['border'], // border
                $break ? 1 : 0, // break
                $options['align'], // align
                0, // fill
                '',  // link
                0, // stretch
                false, // ignore_min_height
                'T', // calign
                $options['valign'] // valign
            );
        }

        // if simulate, save the options
        if($this->simulate) {
            $this->cols[] = [
                $txt, 
                // save dynamic height, so all cols have same height as maximum height
                array_merge($options, [
                    'height' => $options['height'] == 0 ? $this->GetLastH() : $options['height']
                ]), 
                $break];
        }
    }

    function rowEnd() {
        // find the heightest column
        $heightest = 0;
        foreach($this->cols as $col) {
            if($col[1]['height'] > $heightest) {
                $heightest = $col[1]['height'];
            }
        }

        // rollback simulation
        $this->rollbackTransaction(true);

        // if not printing header, detect page for page break
        if($this->onHeader === false) {
            $this->detectPageBreak($heightest);
        }
        
        // display immediately
        $this->simulate = false;
        foreach($this->cols as $index => $col) {
            $col[1]['height'] = $heightest;
            if($index == count($this->cols) - 1) {
                $col[2] = true;
            }
            $this->column(...$col);
        }
    }

    function detectPageBreak($rowHeight = 0) {
        $height = $rowHeight;
        if($rowHeight == 0) {
            $height = $this->GetLastH();
        }
        if(($this->GetY() + $height) >= $this->GetPageHeight()) {
            $tmpCols = $this->cols;
            $this->pageBreak();
            $this->cols = $tmpCols;
        }
    }

    function pageBreak() {
        $this->AddPage($this->pageArgs['orientation'], $this->pageArgs['size']);
    }

    function render($name = 'download.pdf') {
        $this->Output($name, 'I');
        die();
    }

}