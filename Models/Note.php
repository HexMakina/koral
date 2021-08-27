<?php

namespace HexMakina\koral\Models;

use \HexMakina\Crudites\Interfaces\SelectInterface;
use \HexMakina\TightORM\TightModel;

class Note extends TightModel implements Interfaces\ServiceEventInterface
{
	use Abilities\ServiceEvent;
	use Abilities\Itemability;
	use Abilities\Gastability;

	public function __toString(){
    preg_match("/(?:\w+(?:\W+|$)){0,5}/", $this->get('content'), $matches);
    return $matches[0].'...';
	}

  public function immortal() : bool
	{
		if(count(Item::filter(['model' => $this]))>0 || count(Gast::filter(['model' => $this]))>0)
			return true;

		return false;
	}

  public function item_types()
  {
		$service_abbrev = $this->get('service_abbrev') ?? Service::abbrevs()[$this->get('service_id')] ?? null;
    $ret=[];
    switch($service_abbrev)
    {
      case Service::GI: 	$ret []= 'guidance_individuelle';
                          $ret []= 'session_location';
      break;
      case Service::PI: 	$ret []= 'site_internet'; break;
      case Service::TDR: 	$ret []= 'lieux'; break;
    }
    $ret[]= 'subjects'; // subject for all!

    return $ret;
  }

	public function before_save() : array
	{
		if(empty($this->get('todo'))) // goddamn checkboxes
			$this->set('todo', 0);

		return [];
	}

	public function kill()
	{
		if($this->immortal())
			return false;

		// $this->set_many([], Gast::otm());
    Gast::set_many([], $this);
    Item::set_many([], $this);
		// $this->set_many([], Item::otm());

		parent::kill();
	}

  public static function first_for_gast_id($gast_id)
	{
		$fields = ['t_from.id', 't_from.occured_on', 't_from.service_id', 'service.abbrev as abbrev'];
		$table_alias = 't_from';
		$Query = static::table()->select($fields, $table_alias);

		//---- JOIN & FILTER  GAST
		$Query->join('service', [['service', 'id', $table_alias, 'service_id']], 'LEFT OUTER');
		$Query->join(['gasts_models', 'gm'], [['gm', 'model_id', $table_alias, 'id'], ['gm', 'model_type', self::model_type()]], 'LEFT OUTER');
		$Query->join([Gast::table_name(), 'g'], [['g', 'id', 'gm', 'gast_id']], 'LEFT OUTER');

		$Query->aw_eq('gast_id', $gast_id, 'gm');
		$Query->order_by(['t_from', 'occured_on', 'ASC']);
		$Query->limit(1);

		$res = static::retrieve($Query);
		if($res === false || empty($res))
			return null;

		$res = array_pop($res);
		return [$res->abbrev => $res->occured_on];
	}

  public static function query_retrieve($filters=[], $options=[]) : SelectInterface
	{
		//---- JOIN & FILTER SERVICE
		$Query = parent::query_retrieve($filters, $options);

		if(isset($filters['service']) && !empty($filters['service']->get_id()))
			$Query->aw_eq('service_id', $filters['service']->get_id());


		// //---- JOIN & FILTER SESSION
		if(isset($filters['session']) && !empty($filters['session']->get_id()))
			$Query->aw_eq('session_id', $filters['session']->get_id());

    if(isset($filters['note_type']))
		{
			switch($filters['note_type'])
			{
				case 'service':
				case 'interne':
					$Query->aw_is_null('session_id');
				break;

				case 'session':
					$Query->aw_not_empty('session_id');
				break;
			}
		}

		//---- JOIN & FILTER  GAST
		$Query->join(['gasts_models', 'gm'], [['gm', 'model_id', $Query->table_label(), 'id'], ['gm', 'model_type', 'note']], 'LEFT OUTER');
		$Query->join([Gast::table_name(), 'g'], [['g', 'id', 'gm', 'gast_id']], 'LEFT OUTER');

		$Query->select_also(['GROUP_CONCAT(DISTINCT gm.gast_id) as gast_ids', 'COUNT(DISTINCT gm.gast_id) as count_gasts', 'GROUP_CONCAT(DISTINCT g.name SEPARATOR ", ") as gast_names']);

		if(isset($filters['gast']) && !empty($filters['gast']->get_id()))
			$Query->aw_eq('gast_id', $filters['gast']->get_id(), 'gm');

		//---- JOIN & FILTER  ITEM
		if(isset($filters['items']) && !empty($filters['items']))
		{
			$Query->join(['items_models', 'im'], [['im', 'model_id', $Query->table_label(), 'id'], ['im', 'model_type', self::model_type()]], 'INNER');
			$Query->aw_numeric_in('item_id', array_keys($filters['items']), 'im');
		}

    if(isset($filters['date_start']))
			$Query->aw_gte('occured_on', $filters['date_start'], $Query->table_label(), ':filter_date_start');

		if(isset($filters['date_stop']))
			$Query->aw_lte('occured_on', $filters['date_stop'], $Query->table_label(), ':filter_date_stop');

    $Query->group_by('id');
		if(!isset($options['order_by']))
			$Query->order_by(['occured_on', 'DESC']);

		// vd($Query);
		return $Query;
  }
}
?>
