<?php

namespace Twf\Pps;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class Escpos
{
	
	public $printer;
	public $char_per_line = 42;
	
	public function load($printer) {
		
		if ($printer->connection_type == 'network') {
			set_time_limit(30);
			$connector = new NetworkPrintConnector($printer->ip_address, $printer->port);
		} elseif ($printer->connection_type == 'linux') {
			$connector = new FilePrintConnector($printer->path);
		} else {
			$connector = new WindowsPrintConnector($printer->path);
		}

		$this->char_per_line = $printer->char_per_line;

        $profile = CapabilityProfile::load($printer->capability_profile);
		$this->printer = new Printer($connector, $profile);
	}

	public function print_invoice($data) {

		//Print logo
		if (isset($data->logo) && !empty($data->logo)) {
			$logo = $this->download_image($data->logo);

			$this->printer->setJustification(Printer::JUSTIFY_CENTER);

			$logo = EscposImage::load($logo, false);
			//$this->printer->graphics($logo);
			$this->printer->bitImage($logo);
		}
		
		/* Header */
		$this->printer->setJustification(Printer::JUSTIFY_CENTER);
		$this->printer->setEmphasis(true);
		$this->printer->setTextSize(2, 2);
		if (isset($data->header_text) && !empty($data->header_text)) {
			$this->printer->text(strip_tags($data->header_text));
			$this->printer->feed();
		}

		/* Shop & Location Name */
		if (isset($data->display_name) && !empty($data->display_name)) {
			$this->printer->text($data->display_name);
			$this->printer->feed();
		}

		/* Shop Address */
		$this->printer->setTextSize(1, 1);
		if (isset($data->address) && !empty($data->address)) {
			$this->printer->text($data->address);
			$this->printer->feed(2);
		}

		/* Custom 5 lines */
		if(!empty($data->sub_heading_line1)){
			$this->printer->text($data->sub_heading_line1);
			$this->printer->feed(1);
		}
		if(!empty($data->sub_heading_line2)){
			$this->printer->text($data->sub_heading_line2);
			$this->printer->feed(1);
		}
		if(!empty($data->sub_heading_line3)){
			$this->printer->text($data->sub_heading_line3);
			$this->printer->feed(1);
		}
		if(!empty($data->sub_heading_line4)){
			$this->printer->text($data->sub_heading_line4);
			$this->printer->feed(1);
		}
		if(!empty($data->sub_heading_line5)){
			$this->printer->text($data->sub_heading_line5);
			$this->printer->feed(1);
		}

		/* Tax 1 & tax 2 info */
		if(!empty($data->tax_info1) || !empty($data->tax_info2)){

			if(!empty($data->tax_info1)){
				$this->printer->setEmphasis(true);
					$this->printer->text($data->tax_label1);
				$this->printer->setEmphasis(false);

				$this->printer->text($data->tax_info1);
				$this->printer->feed();
			}

			if(!empty($data->tax_info2)){
				$this->printer->setEmphasis(true);
					$this->printer->text($data->tax_label2);
				$this->printer->setEmphasis(false);

				$this->printer->text($data->tax_info2);
				$this->printer->feed();
			}
		}

		/* Title of receipt */
		if (isset($data->invoice_heading) && !empty($data->invoice_heading)) {
			$this->printer->setEmphasis(true);
			$this->printer->text($data->invoice_heading);
			$this->printer->setEmphasis(false);
			$this->printer->feed(1);
		}

		$this->printer->setJustification(Printer::JUSTIFY_LEFT);
		
		//Bolded labels

		// //Invoice  number 
		// $this->printer->feed(1);
		// $this->printer->setEmphasis(true);
		// 	$this->printer->text($data->invoice_no_prefix);
		// $this->printer->setEmphasis(false);
		// $this->printer->text(' ' . $data->invoice_no);

		// // & Date
		// $this->printer->setEmphasis(true);
		// 	$this->printer->text(' ' . $data->date_label);
		// $this->printer->setEmphasis(false);
		// $this->printer->text(' ' . $data->invoice_date);

		//Customer info with bolded labels.
		// if(!empty($data->customer_info) || !empty($data->client_id)){

		// 	$this->printer->feed();

		// 	if(!empty($data->customer_info)){
		// 		$this->printer->setEmphasis(true);
		// 			$this->printer->text($data->customer_label);
		// 		$this->printer->setEmphasis(false);
		// 		$this->printer->text(' ' . $data->customer_info);
		// 	}

		// 	if(!empty($data->client_id)){
		// 		$this->printer->setEmphasis(true);
		// 			$this->printer->text(' ' . $data->client_id_label);
		// 		$this->printer->setEmphasis(false);
		// 		$this->printer->text(' ' . $data->client_id . "\n");
		// 	}
		// }
		//End of Bolded labels


		//invoice info with tabled style
		$this->printer->feed(1);
		$invoice_no = $data->invoice_no_prefix;
		$invoice_no .= ' ' . $data->invoice_no;

		// & Date
		$date = $data->date_label;
		$date .= ' ' . $data->invoice_date;

		$this->printer->text(rtrim($this->columnify($invoice_no, $date, 50, 50, 0,0)));
		$this->printer->feed();

		//Customer info with tabled style
		if(!empty($data->customer_info) || !empty($data->client_id)){

			$customer_info = '';
			if(!empty($data->customer_info)){
				$customer_info = $data->customer_label;
				$customer_info .= ' ' . $data->customer_info;
			}

			$client_id = '';
			if(!empty($data->client_id)){
				$client_id = $data->client_id_label;
				$client_id .= ' ' . $data->client_id;
			}

			$this->printer->text(rtrim($this->columnify($customer_info, $client_id, 50, 50, 0,0)));
			$this->printer->feed();
		}

		//Show products list
		if (isset($data->lines) && !empty($data->lines)) {

			//Print heading
			//QTY, ITEM, PRICE, TOTAL
			//10,		40, 25,25

			$this->printer->text($this->drawLine());
			$string = $this->columnify($this->columnify($this->columnify($data->table_qty_label, $data->table_product_label, 10,40,0,0), $data->table_unit_price_label, 50,25,0,0), $data->table_subtotal_label, 75,25,0,0);
			$this->printer->setEmphasis(true);
			$this->printer->text(rtrim($string));
			$this->printer->feed();
			$this->printer->setEmphasis(false);
			$this->printer->text($this->drawLine());

			foreach ($data->lines as $key => $line) {
				$line = (array)$line;

				//Generate product name
				$product = $line['name'] . ' ' . $line['variation'];
				//sell_line_note
				if(!empty($line['sell_line_note'])){
					$product = $product . '(' . $line['sell_line_note'] . ')';
				}
				//Sku
				if(!empty($line['sub_sku'])){
					$product = $product . ', ' . $line['sub_sku'];
				}
				//brand
				if(!empty($line['brand'])){
					$product = $product . ', ' . $line['brand'];
				}
				//cat_code
				if(!empty($line['cat_code'])){
					$product = $product . ', ' . $line['cat_code'];
				}

				$quantity = $line['quantity'];

				$unit_price = $line['unit_price_inc_tax'];

				$line_total = $line['line_total'];

				$string = rtrim($this->columnify($this->columnify($this->columnify($quantity, $product,10,40,0,0), $unit_price, 50,25,0,0), $line_total, 75,25,0,0));

				$this->printer->text($string);
				$this->printer->feed(2);
			}

			$this->printer->feed();
			$this->printer->text($this->drawLine());
		}

		//SubTotal, Discount, Tax, Total
		if (isset($data->subtotal) && !empty($data->subtotal)) {
			$subtotal = $this->columnify($data->subtotal_label, $data->subtotal, 50, 50, 0,0);
			$this->printer->text(rtrim($subtotal));
			$this->printer->feed();
		}
		if (isset($data->discount) && !empty($data->discount) && $data->discount != 0) {
			$discount = $this->columnify($data->discount_label, $data->discount, 50, 50, 0,0);
			$this->printer->text(rtrim($discount));
			$this->printer->feed();
		}
		if (isset($data->tax) && !empty($data->tax) && $data->tax != 0) {
			$tax = $this->columnify($data->tax_label, $data->tax, 50, 50, 0,0);
			// $tax = $this->columnify('', $data->tax_label . ' ' . $data->tax, 40, 60, 0,0);
			$this->printer->text(rtrim($tax));
			$this->printer->feed();
		}
		if (isset($data->total) && !empty($data->total)) {
			$this->printer->setEmphasis(true);
			$total = $this->columnify($data->total_label, $data->total, 50, 50, 0,0);
			$this->printer->text(rtrim($total));
			$this->printer->feed();
			$this->printer->setEmphasis(false);
		}
		if (isset($data->total_paid) && !empty($data->total_paid)) {
			$total_paid = $this->columnify($data->total_paid_label, $data->total_paid, 50, 50, 0,0);
			$this->printer->text(rtrim($total_paid));
			$this->printer->feed();
		}
		if (isset($data->total_due) && !empty($data->total_due) && $data->total_due != 0) {
			$total_due = $this->columnify($data->total_due_label, $data->total_due, 50, 50, 0,0);
			$this->printer->text(rtrim($total_due));
			$this->printer->feed();
		}
		
		$this->printer->text($this->drawLine());
		
		if (isset($data->footer_text) && !empty($data->footer_text)) {
			$this->printer->setJustification(Printer::JUSTIFY_CENTER);
			$this->printer->feed(1);
			$this->printer->text(strip_tags($data->footer_text). "\n");
			$this->printer->feed();
		}

		//Barcode
		// if (isset($data->barcode) && !empty($data->barcode)) {
		// 	$this->printer->setBarcodeHeight(40);
		// 	$this->printer->setBarcodeWidth(2);
		// 	$this->printer->selectPrintMode();
		// 	$this->printer->setBarcodeTextPosition(Printer::BARCODE_TEXT_BELOW);
		// 	$this->printer->barcode($data->barcode, Printer::BARCODE_CODE39);
		// 	$this->printer->feed();
		// }

		
		$this->printer->feed();
		$this->printer->cut();

		if (isset($data->cash_drawer) && !empty($data->cash_drawer)) {
			$this->printer->pulse();
		}

		$this->printer->close();
	}

	public function open_drawer() {
		
		$this->printer->pulse();
		$this->printer->close();
		
	}
	
	function drawLine() {
		
		$new = '';
		for ($i = 1; $i < $this->char_per_line; $i++) {
			$new .= '-';
		}
		return $new . "\n";
		
	}
	
	function printLine($str, $size = NULL, $sep = ":", $space = NULL) {
		if (!$size) {
			$size = $this->char_per_line;
		}
		$size = $space ? $space : $size;
		$length = strlen($str);
		list($first, $second) = explode(":", $str, 2);
		$line = $first . ($sep == ":" ? $sep : '');
		for ($i = 1; $i < ($size - $length); $i++) {
			$line .= ' ';
		}
		$line .= ($sep != ":" ? $sep : '') . $second;
		return $line;
	}

	/**
	 * Arrange ASCII text into columns
	 * 
	 * @param string $leftCol
	 *            Text in left column
	 * @param string $rightCol
	 *            Text in right column
	 * @param number $leftWidthPercent
	 *            Width of left column
	 * @param number $rightWidthPercent
	 *            Width of right column
	 * @param number $space
	 *            Gap between columns
	 * @param number $remove_for_space
	 *            Remove the number of characters for spaces
	 * @return string Text in columns
	 */
	function columnify($leftCol, $rightCol, $leftWidthPercent, $rightWidthPercent, $space = 2, $remove_for_space = 0)
	{
		$char_per_line = $this->char_per_line - $remove_for_space;

		$leftWidth = $char_per_line * $leftWidthPercent/100;
		$rightWidth = $char_per_line * $rightWidthPercent/100;

	    $leftWrapped = wordwrap($leftCol, $leftWidth, "\n", true);
	    $rightWrapped = wordwrap($rightCol, $rightWidth, "\n", true);

	    $leftLines = explode("\n", $leftWrapped);
	    $rightLines = explode("\n", $rightWrapped);
	    $allLines = array();
	    for ($i = 0; $i < max(count($leftLines), count($rightLines)); $i ++) {
	        $leftPart = str_pad(isset($leftLines[$i]) ? $leftLines[$i] : "", $leftWidth, " ");
	        $rightPart = str_pad(isset($rightLines[$i]) ? $rightLines[$i] : "", $rightWidth, " ");
	        $allLines[] = $leftPart . str_repeat(" ", $space) . $rightPart;
	    }
	    return implode($allLines, "\n") . "\n";
	}

	/**
	 * Check if image is not present than check download and save it.
	 * 
	 * @param string $url
	 */
	function download_image($url){

		$file = basename($url);

		$logo_directory = realpath(dirname(__FILE__) . '/../logos/');
		$logo_image = $logo_directory . '/' . $file;
		
		//Check if the file exists
		//If not, download and store it.
		//Reurn the file path
		$success = true;
		if(!file_exists($logo_image)){
			$image_content = file_get_contents($url);
			$success = file_put_contents($logo_image, $image_content);
		}
		
		if($success){
			return $logo_image;
		} else {
			return false;
		}
	}
}
