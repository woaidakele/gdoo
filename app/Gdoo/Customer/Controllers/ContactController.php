<?php namespace Gdoo\Customer\Controllers;

use DB;
use Request;
use Validator;

use Gdoo\Model\Grid;
use Gdoo\Model\Form;
use Gdoo\Customer\Models\Customer;
use Gdoo\Customer\Models\Contact;

use Gdoo\Index\Controllers\DefaultController;

class ContactController extends DefaultController
{
    public $permission = ['dialog'];

    public function indexAction()
    {
        $header = Grid::header([
            'code' => 'customer_contact',
            'referer' => 1,
            'search' => ['by' => ''],
        ]);

        $cols = $header['cols'];

        // 客户权限
        $region = regionCustomer('customer_id_customer');
 
        $cols['actions']['options'] = [[
            'name'  => '编辑',
            'action' => 'edit',
            'display' => $this->access['edit'],
        ]];

        $search = $header['search_form'];
        $query = $search['query'];

        if (Request::method() == 'POST') {
            $model = DB::table($header['table'])->setBy($header);
            foreach ($header['join'] as $join) {
                $model->leftJoin($join[0], $join[1], $join[2], $join[3]);
            }
            $model->orderBy($header['sort'], $header['order'])
            ->orderBy('id', 'desc');

            foreach ($search['where'] as $where) {
                if ($where['active']) {
                    $model->search($where);
                }
            }

            if ($region['authorise']) {
                foreach ($region['whereIn'] as $key => $where) {
                    $model->whereIn($key, $where);
                }
            }

            $model->select($header['select']);
            $rows = $model->paginate($query['limit'])->appends($query);
            $items = Grid::dataFilters($rows, $header);
            return $items->toJson();
        }

        $header['buttons'] = [
            ['name' => '删除', 'icon' => 'fa-remove', 'action' => 'delete', 'display' => $this->access['delete']],
        ];
        $header['cols'] = $cols;
        $header['tabs'] = Contact::$tabs;
        $header['bys']  = Contact::$bys;
        $header['js']   = Grid::js($header);

        return $this->display([
            'header' => $header,
        ]);
    }

    // 新建客户联系人
    public function createAction($action = 'edit')
    {
        $id = (int)Request::get('id');
        $form = Form::make(['code' => 'customer_contact','id' => $id, 'action' => $action]);
        return $this->display([
            'form' => $form,
        ], 'create');
    }

    // 创建客户联系人
    public function editAction()
    {
        return $this->createAction();
    }

    // 显示客户联系人
    public function showAction()
    {
        return $this->createAction('show');
    }

    // 删除
    public function deleteAction()
    {
        if (Request::method() == 'POST') {
            $ids = Request::get('id');
            return Form::remove(['code' => 'customer_contact', 'ids' => $ids]);
        }
    }

    /**
     * 弹出层信息
     */
    public function dialogAction()
    {
        $header = Grid::header([
            'code'  => 'customer_contact',
        ]);
        $search = $header['search_form'];
        $query = $search['query'];

        if (Request::method() == 'POST') {
            $model = DB::table($header['table']);
            foreach ($header['join'] as $join) {
                $model->leftJoin($join[0], $join[1], $join[2], $join[3]);
            }
            $model->orderBy($header['sort'], $header['order']);

            foreach ($search['where'] as $where) {
                if ($where['active']) {
                    $model->search($where);
                }
            }

            // 客户圈权限
            $region = regionCustomer();
            if ($region['authorise']) {
                foreach ($region['whereIn'] as $key => $where) {
                    $model->whereIn($key, $where);
                }
            }

            if ($query['region_id']) {
                $model->where('customer.region_id', $query['region_id']);
            }

            $model->select($header['select']);

            $rows = $model->paginate($query['limit']);
            $items = Grid::dataFilters($rows, $header, function($item) {
                $item['text'] = $item['name'];
                return $item;
            });
            return response()->json($items);
        }
        $query['form_id'] = $query['jqgrid'] == '' ? $query['id'] : $query['jqgrid'];
        return $this->render([
            'search' => $search,
            'query' => $query,
        ]);
    }
}
