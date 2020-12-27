<?php

namespace App\Admin\Controllers;

use App\Models\CrmContract;
use App\Admin\Traits\Customfields;
use App\Models\CrmProduct;
use App\Admin\Renderable\CrmCustomerTable;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Admin;
use App\Models\CrmCustomer;

class ContractController extends AdminController
{
    use Customfields;
    public static $css = [
        '/static/css/contract_show.css',
    ];
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        // dd(date("Y-m-d", strtotime("-7 day")));

        if (!Admin::user()->isRole('administrator')) {
            $contract = CrmContract::whereHas('CrmCustomer', function ($query) {
                $query->where('admin_user_id', Admin::user()->id);
            })->with(['CrmReceipts']);
        } else {
            $contract = CrmContract::with(['CrmReceipts']);
        }

        return Grid::make($contract, function (Grid $grid) {
            $grid->showColumnSelector();
            $grid->selector(function (Grid\Tools\Selector $selector) {
                $selector->select('status', '状态', [
                    1 => '未开始',
                    2 => '执行中',
                    3 => '正常结束',
                    4 => '意外终止',
                ]);
                $selector->select('signdate', '签订日期', ['3天内', '7天内', '15天内', '1月内', '2月内'], function ($query, $value) {
                    $between = [
                        [date("Y-m-d", strtotime("-3 day")), date("Y-m-d")],
                        [date("Y-m-d", strtotime("-7 day")), date("Y-m-d")],
                        [date("Y-m-d", strtotime("-15 day")), date("Y-m-d")],
                        [date("Y-m-d", strtotime("-1 month")), date("Y-m-d")],
                        [date("Y-m-d", strtotime("-2 month")), date("Y-m-d")],
                    ];

                    $value = current($value);
                    $query->whereBetween('signdate', $between[$value]);
                });
                $selector->select('expiretime', '到期时间', ['3天内', '7天内', '15天内', '1月内', '2月内'], function ($query, $value) {
                    $between = [
                        [date("Y-m-d"), date("Y-m-d", strtotime("+3 day"))],
                        [date("Y-m-d"), date("Y-m-d", strtotime("+7 day"))],
                        [date("Y-m-d"), date("Y-m-d", strtotime("+15 day"))],
                        [date("Y-m-d"), date("Y-m-d", strtotime("+1 month"))],
                        [date("Y-m-d"), date("Y-m-d", strtotime("+2 month"))],
                    ];

                    $value = current($value);
                    $query->whereBetween('expiretime', $between[$value]);
                });
            });


            $grid->status
                ->using(
                    [
                        1 => '未开始',
                        2 => '执行中',
                        3 => '正常结束',
                        4 => '意外终止'
                    ]
                )->dot(
                    [
                        1 => 'dark85',
                        2 => 'green',
                        3 => 'dark',
                        4 => 'red-darker',
                    ],
                    'dark85' // 第二个参数为默认值
                );

            $grid->title->link(function () {
                return admin_url('contracts/' . $this->id);
            });
            $grid->crm_customer_id('所属客户')->display(function ($id) {
                return optional(CrmCustomer::find($id))->name;
            })->link(function () {
                return admin_url('customers/' . $this->crm_customer_id);
            });
            $grid->signdate->sortable();
            $grid->expiretime->sortable();
            $grid->total;
            $grid->CrmReceipts->display(function ($receipts) {
                $count = count($receipts);
                if ($count) {
                    $accepts = 0;
                    foreach ($receipts as $value) {
                        $accepts += $value['receive'];
                    }
                } else {
                    $accepts = 0;
                }

                if ($this->total - $accepts) {
                    $payback = $this->total - $accepts;
                    $payback = "<span style='font-weight: 700;' class='text-danger'>$payback</span>";
                } else {
                    $payback = "<span style='font-weight: 700;' class='text-primary'>已结清</span>";
                }
                return $payback;
            });
            $this->gridfield($grid,'contract');
            if (Admin::user()->isRole('administrator')) {
                $top_titles = [
                    'id' => 'ID',
                    'title' => '合同名称',
                    'crm_customer_id' => '所属客户',
                    'signdate' => '签订日期',
                    'expiretime' => '到期时间',
                    'total' => '合同总额',
                    'status' => '合同状态'
                ];
                $grid->export($top_titles)->rows(function (array $rows) {
                    foreach ($rows as $index => &$row) {
                        $row['crm_customer_id'] = CrmCustomer::find($row['crm_customer_id'])->name;
                        switch ($row['status']) {
                            case 1:
                                $row['status'] = '未开始';
                                break;
                            case 2:
                                $row['status'] = '执行中';
                                break;
                            case 3:
                                $row['status'] = '正常结束';
                                break;
                            default:
                                $row['status'] = '意外终止';
                        }
                    }
                    return $rows;
                });
            }

            $grid->model()->orderBy('id', 'desc');
            $grid->disableBatchActions();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
                $filter->like('title', '合同名称');
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */

    public function show($id, Content $content)
    {

        $detalling = Admin::user()->id != CrmContract::find($id)->CrmCustomer->Admin_user->id;
        $Role = !Admin::user()->isRole('administrator');
        if ($Role && $detalling) {
            $Contract = CrmContract::find($id);
            $this->authorize('update', $Contract);
        }

        Admin::css(static::$css);
        $contract = CrmContract::query()->findorFail($id);
        $customer = CrmContract::find($id)->CrmCustomer;
        $receipts = CrmContract::find($id)->CrmReceipts;
        $events = CrmContract::find($id)->CrmEvents;
        $attachments = CrmContract::find($id)->attachments()->orderBy('updated_at', 'desc')->get();
        $admin_user = CrmContract::find($id)->CrmCustomer->Admin_user;
        $accept = json_decode($receipts);
        $accepts = 0;
        foreach ($accept as $value) {
            $accepts += $value->receive;
        }

        $data = [
            'contract' => $contract,
            'customer' => $customer,
            'receipts' => $receipts,
            'accepts' => $accepts,
            'events' => $events,
            'admin_user' => $admin_user,
            'attachments' => $attachments,
            'contractfields' => $this->custommodel('contract'),
        ];
        return $content
            ->title('合同')
            ->description('详情')
            ->body($this->_detail($data));
    }
    private function _detail($data)
    {
        return view('admin/contract/show', $data);
    }



    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new CrmContract(), function (Form $form) {
            // $Editing = $form->isEditing() && Admin::user()->id != CrmCustomer::find($form->model()->customer_id)->admin_user_id;
            // if ($Editing) {
            //     $customer = CrmCustomer::find($form->model()->id);
            //     $this->authorize('update', $customer);
            // }

            Admin::css(static::$css);


            $form->column(6, function (Form $form) {
                $form->text('title')->required();
                $form->selectTable('crm_customer_id')
                    ->title('弹窗标题')
                    ->dialogWidth('50%') // 弹窗宽度，默认 800px
                    ->from(CrmCustomerTable::make(['id' => $form->getKey()])) // 设置渲染类实例，并传递自定义参数
                    ->model(CrmCustomer::class, 'id', 'name'); // 设置编辑数据显示
                $form->date('signdate', '签署时间')->required();
            });


            $form->column(6, function (Form $form) {
                $form->display('id');
                $form->select('status', '合同状态')->options([1 => '未开始', 2 => '执行中', 3 => '正常结束', 4 => '意外终止']);
                $form->date('expiretime', '到期时间')->required();
            });



            $form->column(12, function (Form $form) {
                $form->table('order', '订单', function ($table) {
                    $table->select('prodname', '产品')->options(CrmProduct::pluck('name', 'id'));
                    // $table->currency('prodprice', '标准价')->symbol('￥');
                    $table->currency('executionprice', '成交单价')->symbol('￥');
                    $table->number('quantity', '数量')->attribute('min', 1)->default(1);
                    // $table->text('unit', '单位')->disable();
                })->saving(function ($v) {
                    return json_encode($v);
                });
            });

            // $form->column(12, function (Form $form) {
            //     $form->html('
            //     <div class="fill">
            //         <span class="xcm">合计成交价</span>
            //         <span class="xco fsm">￥</span>
            //         <em class="xco fsl" id="ctrt_prod_total">0</em>
            //     </div>
            //     ');
            // });

            $form->column(6, function (Form $form) {
                $form->currency('total', '合同金额')->symbol('￥')->attribute('min', 0)->default(0);
            });

            $form->column(6, function (Form $form) {
                $form->currency('salesexpenses', '商务费用')->symbol('￥')->attribute('min', 0)->default(0);
            });

            $form->column(12, function (Form $form) {
                $form->textarea('remark', '备注');
                $this->formfield($form,'contract');
                $form->hidden('fields')->value(null);
            });

            $class = $this;
            $form->saving(function (Form $form) use ($class) {
                if ($form->salesexpenses || $form->total) {
                    $form->salesexpenses = str_replace(',', '', $form->salesexpenses);
                    $form->total = str_replace(',', '', $form->total);
                }
                $order = $form->order;
                foreach ($order as $key => $value) {
                    $productid = $order[$key]['prodname'];
                    $order[$key]['executionprice'] = str_replace(',', '', $order[$key]['executionprice']);
                    $order[$key]['prodprice'] = CrmProduct::find($productid)->price;
                }
                $form->order = $order;

                $form_field = array();
                foreach ($class->custommodel('CrmContract') as $field) {
                    $field_field = $field['field'];
                    $form_field[$field_field] = $form->$field_field;
                    $form->deleteInput($field['field']);
                }
                // dd(json_encode($form_field));
                $form->fields = json_encode($form_field);

                return $form;
            });
        });
    }
}
