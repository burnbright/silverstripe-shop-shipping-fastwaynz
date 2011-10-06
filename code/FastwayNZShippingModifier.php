<?php

/**
 * Works out the cheapest shipping based on the total order weight and the NZ Fastway rates
 * http://www.fastway.co.nz/6Prices.html
 */

class FastwayNZShippingModifier extends OrderModifier{

	static $db = array(
		'Region' => 'Varchar',
		'Rural' => 'Boolean'
	);

	//prices last updated 30 Nov 2010

	static $ruralfee = 3.85;

	static $standard = array(
		'local' => array(
			'price' => 3.25,
			'maxweight' => 25
		),
		'shorthaul' => array(
			'price' => 6.95,
			'maxweight' => 25
		),
		'withinisland' => array(
			'price' => 10.10,
			'maxweight' => 10,
			'excess' => array(
				'price' => 4.85,
				'weight' => 5
			)
		),
		'betweenislands5kg' => array(
			'price' => 10.85,
			'maxweight' => 5,
		),
		'betweenislands10kg' => array(
			'price' => 17.45,
			'maxweight' => 10,
			'excess' => array(
				'price' => 9.70,
				'weight' => 5
			)
		),
		'smallparcelsnationwide' => array(
			'price' => 5.75,
			'maxweight' => 2
		)
	);

	static $frequent = array(
		'local' => array(
			'price' => 2.20,
			'maxweight' => 25
		),
		'shorthaul' => array(
			'price' => 5.95,
			'maxweight' => 25
		),
		'withinisland' => array(
			'price' => 9.10,
			'maxweight' => 10,
			'excess' => array(
				'price' => 3.85,
				'weight' => 5
			)
		),
		'betweenislands5kg' => array(
			'price' => 9.85,
			'maxweight' => 5,
		),
		'betweenislands10kg' => array(
			'price' => 16.45,
			'maxweight' => 10,
			'excess' => array(
				'price' => 7.70,
				'weight' => 5
			)
		),
		'smallparcelsnationwide' => array(
			'price' => 5.05,
			'maxweight' => 2
		)
	);

	static $regionoptions = array(
		"Northland" => array('smallparcelsnationwide','withinisland'),
		"Auckland" => array('smallparcelsnationwide','withinisland'),
		"Waikato" => array('smallparcelsnationwide','withinisland'),
		"Bay of Plenty" => array('smallparcelsnationwide','withinisland'),
		"East Coast" => array('smallparcelsnationwide','withinisland'),
		"Taranaki" => array('smallparcelsnationwide','withinisland'),
		"Wanganui" => array('smallparcelsnationwide','withinisland'),
		"Manawatu" => array('smallparcelsnationwide','withinisland'),
		"Wellington" => array('shorthaul','smallparcelsnationwide','withinisland'),
		"Nelson" => array('smallparcelnationwide','betweenislands5kg','betweenislands10kg'),
		"Malborough" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg'),
		"West Coast" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg'),
		"Canterbury" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg'),
		"Otago" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg'),
		"Fiordland" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg'),
		"Southland" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg'),
		"default" => array('smallparcelsnationwide','betweenislands5kg','betweenislands10kg')
	);


	function TableTitle(){return $this->getTableTitle();}
	function getTableTitle(){
		$toregion = (isset(self::$regionoptions[$this->Region])) ? " to ".$this->Region : "";
		if($this->Rural) $toregion .=" - Rural";
		$toregion .= " (weight = ".$this->TotalWeight()."kg)";
		return 'Shipping'.$toregion;
	}

	function getCMSFields(){
		$fields = parent::getCMSFields();
		$keys = array_keys($this->regionoptions);
		$newArray = array();
		foreach($keys as $key) {
			$newArray[$key] = $key;
		}
		$fields->replaceField("Region", new DropdownField("Region", "Region", $newArray));
		return $fields;
	}


	function TotalWeight(){
		$totalweight = 0;
		if($orderItems = $this->Order()->Items()) {
			foreach($orderItems as $orderItem){
				 $totalweight += (float)$orderItem->Product()->Weight * $orderItem->Quantity;
			}
		}
		return $totalweight;
	}

	function Amount() {

		$totalweight = $this->TotalWeight();

		$cost = 0;

		$plans = self::$standard; //make this configurable

		if($totalweight <= 0) return 0;

		$regionoptions = self::$regionoptions;
		$region = 'default';
		$regionoption = null;
		if(isset($regionoptions[$this->Region])) $region = $this->Region;


		if(isset($regionoptions[$region])){
			$regionoption = $regionoptions[$region];
		}elseif(isset($regionoptions['default'])){
			$regionoption = $regionoptions['default'];
		}

		foreach($regionoption as $option){

			if(isset($plans[$option]) && isset($plans[$option]['maxweight']) && isset($plans[$option]['price'])){

				if($totalweight <= $plans[$option]['maxweight']){
					if($cost <= 0 || $plans[$option]['price'] < $cost)
						$cost = $plans[$option]['price'];
				}elseif(isset($plans[$option]['excess'])
					&& is_array($plans[$option]['excess'])
					&& isset($plans[$option]['excess']['price'])
					&& isset($plans[$option]['excess']['weight'])){

						$tempweight =  $plans[$option]['maxweight'];
						$tempcost = $plans[$option]['price'];
						while($tempweight < $totalweight){ //TODO: this can probably be done smarter with modulus or something
							$tempweight += $plans[$option]['excess']['weight'];
							$tempcost += $plans[$option]['excess']['price'];
						}

						if($cost <= 0 || $tempcost < $cost)
							$cost = $tempcost;

				}
			}
		}

		if($this->Rural)
			$cost += self::$ruralfee;

		return $cost;
	}

		//TODO: go into OrderModifier
	function Form(){
		$class = $this->class.'_Controller';
		$cont = new $class();
		$form = $cont->Form();
		$form->loadDataFrom($this);
		$form->Fields()->fieldByName('OrderModifierID')->setValue($this->ID);
		return $form;
	}


}

class FastwayNZShippingModifier_Controller extends Controller{

	//TODO: go into OrderModifier_Controller
	function modifier(){

		if(isset($_REQUEST['OrderModifierID'])
			&& is_numeric($_REQUEST['OrderModifierID'])
			&& $modifier = DataObject::get_by_id('OrderModifier',$_REQUEST['OrderModifierID'])){  //TODO: use proper api
				//TODO: don't allow modification of any modifier
			return $modifier;
		}
		return false;
	}

	//TODO: go into OrderModifier_Controller
	function Link(){
		return $this->class;
	}

	function Form(){

		$regions = FastwayNZShippingModifier::$regionoptions;
		foreach($regions as $key => $values){
			$regions[$key] = $key;
		}

		$form = new Form(
			$this,'Form',
			new FieldSet(
				new DropdownField('Region','Region',$regions),
				new CheckboxField('Rural','Rural'),
				$hf = new HiddenField('OrderModifierID')
			),

			new FieldSet(new FormAction('updatemodifier','update region'))
		);

		return $form;
	}

	//TODO: go into OrderModifier_Controller
	function updatemodifier($data,$form){

		if($modifier = $this->modifier()){
			$form->saveInto($modifier);
			$modifier->write();
		}

		if(!$this->isAjax()) //TODO: use nicer status updates
			Director::redirectBack();
	}



}

?>
