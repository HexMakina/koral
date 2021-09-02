<?php

namespace HexMakina\koral\Controllers;

use \HexMakina\kadro\Auth\{Operator,Permission};
use \HexMakina\Crudites\CruditesException;

class WorkerController extends \HexMakina\kadro\Controllers\ORMController
{
    public function prepare()
    {
        parent::prepare();

        $operator_data = null;
        switch ($this->router()->name()) {
            case 'my_profile':
                $operator_data = ['username' => $this->operator()->username()];
                break;

            case 'profile':
                $operator_data = $this->router()->params();
                break;
        }

        if (!is_null($operator = get_class($this->operator())::exists($operator_data))) {
            $host = $this->get('WorkerClass')::one(['operator_id' => $operator->operator_id()]);
            $this->load_model = $host;
            $this->formModel($this->load_model);
        }

        $this->viewport('permissions', Permission::filter());
    }

    public function dashboard()
    {
        return $this->dashboard_listing(null, null, ['active' => true]);
    }

    protected function dashboard_listing($model = null, $template = null, $filters = [], $options = [])
    {
        $model = $model ?? $this->load_model ?? $this->formModel();

        $this->listing($model, $filters, $options);
        $this->viewport('listing_template', $template, true);
        return 'listing';
    }

    public function edit()
    {
      // dd($this->load_model);
        parent::edit();
      // do we create? or do we edit someone else ? must be admin
        if (is_null($this->load_model) || $this->operator()->operator_id() !== $this->load_model->operator_id()) {
            $this->authorize('group_admin');
        }

        $operator = $this->formModel()->extract(new Operator(), true);
        if ($this->router()->submits()) {
            $this->formModel()->set_operator($operator);
        }

        if (is_null($this->formModel()->operator())) {
            $this->formModel()->set_operator(new Operator());
        }

        $this->viewport('operator', $operator);
        $this->viewport('worker', $this->formModel());


        $this->related_listings();
    }

    protected function related_listings($model = null)
    {
        $model = $model ?? $this->load_model;

        if (is_null($model) || $model->is_new()) {
            return [];
        }

        $related_listings = [];
        $related_listings['permission'] = Permission::filter();

        $this->viewport('related_listings', $related_listings);
        return $related_listings;
    }

    public function save()
    {
        if (is_null($this->load_model)) { // worker creation
            $operator = $this->formModel()->extract(new Operator(), true); // extract operator_* fields content
        } else { // worker alteration
            $operator = Operator::one($this->load_model->operator_id());
        }

      // does the operator wanna change password ?
        if (!empty($new_password = $this->formModel()->get('operator_password')) && !empty($password_confirmation = $this->formModel()->get('operator_password_verification'))) {
            if ($new_password != $password_confirmation) {
                $this->logger()->warning($this->l('KADRO_operator_ERR_PASSWORDS_MISMATCH'));
                return $this->edit();
            }
            $operator->password_change($new_password);
        } else {
            unset($operator->password);
        }

        try {
          // Transaction disabled until problem is solved, ticket opened
          // Worker::connect()->transact();

            $operator = $this->persist_model($operator);
            $this->formModel()->set_operator($operator);

            $this->formModel()->set('operator_id', $operator->get_id());
            $this->persist_model($this->formModel());

          // Worker::connect()->commit();
        } catch (\Exception $e) {
          // Worker::connect()->rollback();
            return $this->edit();
        }

        return $this->router()->hyp('worker');
    }

    public function before_destroy()
    {
        $this->logger()->warning($this->l('MODEL_worker_ERR_cannot_delete_must_disable'));
        $this->router()->hopBack();
    }

    public function route_back($goto = null, $route_params = []): string
    {
        $route = $goto ?? $this->router()->hyp('worker');
        return parent::route_back($route, $route_params);
    }
}
