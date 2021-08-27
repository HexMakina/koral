<?php

namespace HexMakina\koral\Models\Abilities;

use \HexMakina\koral\Models\Customer;

trait Customerability
{
  public $gasts = null;
  public $gast_ids = null;

  public function gasts()
  {
    if(!is_null($this->gasts))
      return $this->gasts;


    if(!$this->is_new())
    {

      if(!is_null($this->get('gast_names')))
      {
        // vd($this->get('gast_names'));
        $gast_names = [];
        if(strpos($this->get('gast_names'), PHP_EOL))
          $gast_names = explode(PHP_EOL, $this->get('gast_names'));

        elseif(strpos($this->get('gast_names'), ','))
          $gast_names = explode(',', $this->get('gast_names'));

        elseif(!empty($this->get('gast_names')))
          $gast_names = [trim($this->get('gast_names'))];

        // vd($gast_names);
        $this->gasts = Gast::by_names($gast_names);

        // vd($this->gasts);
        $this->gast_ids = array_keys($this->gasts);
      }
      else
      {

        $Query = Gast::table()->select(null, 'g');
    		$Query->join(['gasts_models', 'gm'], [['gm', 'gast_id', 'g', 'id'], ['gm', 'model_type', get_class($this)::model_type()], ['gm', 'model_id', $this->get_id()]], 'INNER');

        $this->gasts = Gast::retrieve($Query);
        $this->gast_ids = array_keys($this->gasts);
      }
    }


    return $this->gasts;
  }

  public function gast_alterations($other_model)
  {
    if(!is_null($other_model))
      return count($this->gasts()) != count($other_model->gasts()) || !empty(array_diff($this->gasts(), $other_model->gasts()));
    return false;
  }

  public function GastabilityTraitor_after_save()
  {
    $res = Gast::set_many_by_ids($this->get('gast_ids'),$this);
    // $res = $this->set_many_by_ids($this->get('gast_ids'), Gast::otm());

    if($res === true)
      return 'GAST_GASTABILITY_CHANGES';

    return $res;
  }

}

?>
