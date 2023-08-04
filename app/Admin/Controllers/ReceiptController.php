<?php

namespace App\Admin\Controllers;

use App\Models\CrmReceipt;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Show;
use App\Models\CrmContract;
use Dcat\Admin\Admin;
use App\Models\CrmCustomer;
use App\Admin\Renderable\ContractTable;
use Dcat\Admin\Http\Controllers\AdminController;

class ReceiptController extends AdminController
{
    protected function withCss()
    {
        return <<<CSS
a.btn{
 line-height: 1.22;
}
CSS;

    }
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        Admin::style($this->withCss());
        return Grid::make(CrmReceipt::with(['CrmContract','CrmContract.CrmCustomer']), function (Grid $grid) {
            $grid->model()->orderByDesc('id');
            $grid->updated_at->sortable();
            $grid->receive;
            $grid->paymethod
                ->using(
                    [
                        1 => '银行转账',
                        2 => '微信',
                        3 => '支付宝',
                        4 => '现金',
                        5 => '卡券'
                    ]
                );
            $grid->billtype
                ->using(
                    [
                        0 => '收据',
                        1 => '发票',
                        2 => '其他',
                    ]
                );
            $grid->type
                ->using(
                    [
                        1 => '收款',
                        2 => '支出',
                    ]
                );
            $grid->column('CrmContract.CrmCustomer.name','所属合同')->display(function ($id) {
                return $id.'#'.$this->crm_contract_id;
                // return optional(CrmContract::find($id))->CrmCustomer->name . optional(CrmContract::find($id))->signdate;
            })->link(function () {
                return admin_url('contracts/' . $this->crm_contract_id);
            });


            $grid->remark;
            if (Admin::user()->isRole('administrator')) {
                $top_titles = [
                    'id' => 'ID',
                    'updated_at' => '收款时间',
                    'receive' => '收款金额',
                    'paymethod' => '收款方式',
                    'billtype' => '票据类型',
                    'contract_id' => '所属合同',
                    'remark' => '备注'
                ];
                $grid->export($top_titles)->rows(function (array $rows) {
                    foreach ($rows as $index => &$row) {
                        $row['crm_contract_id'] = CrmContract::find($row['crm_contract_id'])->title;
                        switch ($row['paymethod']) {
                            case 1:
                                $row['paymethod'] = '银行转账';
                                break;
                            case 2:
                                $row['paymethod'] = '微信';
                                break;
                            case 3:
                                $row['paymethod'] = '支付宝';
                                break;
                            case 4:
                                 $row['paymethod'] = '现金';
                                break;
                            default:
                                 $row['paymethod'] = '卡券';
                        }
                        switch ($row['billtype']) {
                            case 0:
                                $row['billtype'] = '收据';
                                break;
                            case 1:
                                $row['billtype'] = '发票';
                                break;
                            default:
                                $row['billtype'] = '其他';
                        }
                    }
                    return $rows;
                });
            }

            $grid->disableDeleteButton();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
            });
            $grid->disableRefreshButton();
            $grid->disableCreateButton();
            $grid->toolsWithOutline(false);
            $grid->disableFilterButton();
            $grid->tools([
                '<a href="'.admin_url('/receipts/create').'" class="btn btn-primary grid-btn"><i class="feather icon-plus"></i><span class="d-none d-sm-inline">&nbsp;&nbsp;新增收款</span></a>',
                '<a href="'.admin_url('/receipts/deposit').'" class="btn btn-primary grid-btn"><i class="feather icon-plus"></i><span class="d-none d-sm-inline">&nbsp;&nbsp;新增支出</span></a>&nbsp;'
            ]);
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        $detalling = Admin::user()->id != CrmCustomer::find(CrmReceipt::find($id)->CrmContract->crm_customer_id)->adminUser->id;
        $Role = !Admin::user()->isRole('administrator');
        if ($Role && $detalling) {
            $customer = CrmCustomer::find($id);
            $this->authorize('update', $customer);
        }

        return Show::make($id, new CrmReceipt(), function (Show $show) {
            $show->id;
            $show->receive;
            $show->paymethod;
            $show->billtype;
            $show->crm_contract_id;
            $show->remark;
            $show->created_at;
            $show->updated_at;
        });
    }

    protected function deposit(Content $content)
    {
        $receipt_type =2;# 支出类型
        return $content
            ->title('新增支出')
            ->description('当前合同支出')
            ->body($this->form($receipt_type));
    }

    /**
     * Make a form builder.
     *
     * @param int $type
     * @return Form
     */
    protected function form(int $type=1)
    {
        return Form::make(CrmReceipt::with('CrmInvoice'), function (Form $form)use($type) {
            $receipt_title = $type === 1?'收款':'支出';

            $form->title('新增'.$receipt_title);
            $form->display('id');
            $form->currency('receive')->symbol('￥');
            $form->hidden('type')->value($type);
            $form->select('paymethod', '收款方式')
                ->options(
                    [
                        1 => '银行转账',
                        2 => '微信',
                        3 => '支付宝',
                        4 => '现金',
                        5 => '卡券'
                    ]
                );
            $selectTable = $form->selectTable('crm_contract_id')
                ->title('选择当前收款所属合同')
                ->dialogWidth('50%') // 弹窗宽度，默认 800px
                ->from(ContractTable::make(['id' => request('crm_contract_id')])) // 设置渲染类实例，并传递自定义参数
                ->model(CrmContract::class, 'id', 'crm_customer_id')
                ->required();// 设置编辑数据显示
            if ($form->isCreating()){
                # 新建时，初始化对应合同
                $selectTable->default(request('crm_contract_id'));
            }

            $form->text('remark')->required();
            $form->datetime('updated_at')->label($receipt_title.'时间');
            $form->radio('billtype', '是否开票')
                ->when(1, function (Form $form) {
                    // 值为1和4时显示文本框
                    $form->divider();

                    $form->currency('crm_invoice.money', '开票金额')->attribute('min', 1);
                    $form->select('crm_invoice.type', '开票类型')
                        ->options([
                            1 => '增值税普通发票',
                            2 => '增值税专用发票',
                            // 3 => '国税通用机打发票',
                            // 4 => '地税通用机打发票',
                            5 => '收据'
                        ]);
                    $form->hidden('crm_invoice.state')->value(0);
                    $form->fieldset('发票信息', function (Form $form) {
                        $form->radio('crm_invoice.title_type', '抬头类型')
                            ->when(1, function (Form $form) {
                                $form->text('crm_invoice.tin', '纳税人识别号');
                                $form->text('crm_invoice.bank_name', '开户行');
                                $form->text('crm_invoice.bank_account', '开户账号');
                                $form->text('crm_invoice.address', '开票地址');
                            })
                            ->options([
                                1 => '单位',
                                2 => '个人',
                            ])
                            ->default('1');
                        $form->text('crm_invoice.title', '发票抬头');
                        $form->mobile('crm_invoice.phone', '电话');
                    });

                    $form->fieldset('邮寄信息', function (Form $form) {
                        $form->text('crm_invoice.contact_name', '联系人');
                        $form->mobile('crm_invoice.contact_phone', '联系电话');
                        $form->text('crm_invoice.contact_address', '邮寄地址');
                    });
                    $form->hidden('crm_invoice.crm_contract_id');
                })
                ->options(
                    [
                        0 => '收据',
                        1 => '发票',
                        2 => '其他',
                    ]
                )->default('0');
            // $form->submitted(function (Form $form) {
            //     dd($form->input('contract_id'));
            //     $form->input('invoice.contract_id') = $form->input('contract_id');
            //     return $form->input('invoice.contract_id');
            // });
            $form->saving(function (Form $form) {
                $invoice = $form->crm_invoice;
                $invoice['crm_contract_id'] = $form->crm_contract_id;
                $form->crm_invoice = $invoice;
                if ($form->billtype === '0' || $form->billtype === '2' ) {
                    $form->deleteInput('crm_invoice.money');
                    $form->deleteInput('crm_invoice.crm_contract_id');
                    $form->deleteInput('crm_invoice.type');
                    $form->deleteInput('crm_invoice.state');
                    $form->deleteInput('crm_invoice.title_type');
                    $form->deleteInput('crm_invoice.tin');
                    $form->deleteInput('crm_invoice.bank_name');
                    $form->deleteInput('crm_invoice.bank_account');
                    $form->deleteInput('crm_invoice.address');
                    $form->deleteInput('crm_invoice.title');
                    $form->deleteInput('crm_invoice.phone');
                    $form->deleteInput('crm_invoice.contact_name');
                    $form->deleteInput('crm_invoice.contact_phone');
                    $form->deleteInput('crm_invoice.contact_address');
                }
                if ($form->receive) {
                    $form->receive = str_replace(',', '', $form->receive);
                }
                return $form;
            });

            $form->saved(function (Form $form) {
                # 重新统计所属合同的商务支出
                $receipt = CrmReceipt::find($form->getKey());
                $contract = $receipt->CrmContract;
                unset($receipt);
                $contract->salesexpenses = $contract->calc_sales_expenses;
                $contract->receipt = $contract->calc_sales_revenue;
                $contract->save();
                return $form->response()->success('保存成功')->redirect('/receipts/');
            });
        });
    }
}
