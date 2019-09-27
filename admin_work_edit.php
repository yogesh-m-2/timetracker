<?php
// +----------------------------------------------------------------------+
// | Anuko Time Tracker
// +----------------------------------------------------------------------+
// | Copyright (c) Anuko International Ltd. (https://www.anuko.com)
// +----------------------------------------------------------------------+
// | LIBERAL FREEWARE LICENSE: This source code document may be used
// | by anyone for any purpose, and freely redistributed alone or in
// | combination with other software, provided that the license is obeyed.
// |
// | There are only two ways to violate the license:
// |
// | 1. To redistribute this code in source form, with the copyright
// |    notice or license removed or altered. (Distributing in compiled
// |    forms without embedded copyright notices is permitted).
// |
// | 2. To redistribute modified versions of this code in *any* form
// |    that bears insufficient indications that the modifications are
// |    not the work of the original author(s).
// |
// | This license applies to this document only, not any other software
// | that it may be combined with.
// |
// +----------------------------------------------------------------------+
// | Contributors:
// | https://www.anuko.com/time_tracker/credits.htm
// +----------------------------------------------------------------------+

require_once('initialize.php');
require 'plugins/work_constants.php';
import('form.Form');
import('ttWorkHelper');
import('ttAdminWorkHelper');

// Access checks.
if (!ttAccessAllowed('administer_site')) {
  header('Location: access_denied.php');
  exit();
}
$cl_work_id = (int)$request->getParameter('id');
$adminWorkHelper = new ttAdminWorkHelper($err);
$work_item = $adminWorkHelper->getWork($cl_work_id);
if (!$work_item) {
  header('Location: access_denied.php');
  exit();
}
// End of access checks.


$existingStatus = $work_item['status'];
$currencies = ttWorkHelper::getCurrencies();

if ($request->isPost()) {
  $cl_name = trim($request->getParameter('work_name'));
  $cl_description = trim($request->getParameter('description'));
  $cl_details = trim($request->getParameter('details'));
  $cl_currency = $request->getParameter('currency');
  $cl_budget = $request->getParameter('budget');
  $cl_status = $request->getParameter('status');
  $cl_moderator_comment = $request->getParameter('moderator_comment');
} else {
  $cl_name = $work_item['subject'];
  $cl_description = $work_item['descr_short'];
  $cl_details = $work_item['descr_long'];
  $currency = $work_item['currency'];
  $cl_currency = array_search($currency, $currencies);
  $cl_budget = $work_item['amount'];
  $cl_status = $work_item['status'];
  $cl_moderator_comment = $work_item['moderator_comment'];
}

$form = new Form('workForm');
$form->addInput(array('type'=>'hidden','name'=>'id','value'=>$cl_work_id));
$form->addInput(array('type'=>'text','maxlength'=>'100','name'=>'work_name','style'=>'width: 250px;','value'=>$cl_name));
$form->addInput(array('type'=>'textarea','name'=>'description','style'=>'width: 250px; height: 40px;','value'=>$cl_description));
$form->addInput(array('type'=>'textarea','name'=>'details','style'=>'width: 250px; height: 80px;','value'=>$cl_details));
$form->addInput(array('type'=>'combobox','name'=>'currency','data'=>$currencies,'value'=>$cl_currency));
$form->addInput(array('type'=>'floatfield','maxlength'=>'10','name'=>'budget','format'=>'.2','value'=>$cl_budget));

// Prepare status choices.
$status_options = array();
$status_options[STATUS_PENDING_APPROVAL] = $i18n->get('dropdown.pending_approval');
$status_options[STATUS_DISAPPROVED] = $i18n->get('dropdown.not_approved');
$status_options[STATUS_APPROVED] = $i18n->get('dropdown.approved');

$form->addInput(array('type'=>'combobox','name'=>'status','value'=>$cl_status,'data'=>$status_options));
$form->addInput(array('type'=>'textarea','name'=>'moderator_comment','style'=>'width: 250px; height: 80px;','value'=>$cl_moderator_comment));
$form->addInput(array('type'=>'submit','name'=>'btn_save','value'=>$i18n->get('button.save')));

if ($request->isPost()) {
  // Validate user input.
  if (!ttValidString($cl_name)) $err->add($i18n->get('error.field'), $i18n->get('label.work'));
  if (!ttValidString($cl_description, true)) $err->add($i18n->get('error.field'), $i18n->get('label.description'));
  if (!ttValidString($cl_details, true)) $err->add($i18n->get('error.field'), $i18n->get('label.details'));
  if (!ttValidString($cl_budget)) $err->add($i18n->get('error.field'), $i18n->get('label.budget'));
  if (!ttValidString($cl_moderator_comment, true)) $err->add($i18n->get('error.field'), $i18n->get('label.moderator_comment'));

  // Ensure user email exists (required for workflow).
  if (!$user->getEmail()) $err->add($i18n->get('error.no_email'));

  if ($err->no()) {
    if ($request->getParameter('btn_save')) {
      $fields = array('work_id'=>$cl_work_id,
        'subject'=>$cl_name,
        'descr_short' => $cl_description,
        'descr_long' => $cl_details,
        'currency' => $currencies[$cl_currency],
        'amount' => $cl_budget,
        'moderator_comment' => $cl_moderator_comment);

      // Do things differently, depending on status control value.
      if ($existingStatus == $cl_status) {
        // Status not changed. Update work information.
        if ($adminWorkHelper->updateWork($fields)) {
          header('Location: admin_work.php');
          exit();
        }
      } else if ($cl_status == STATUS_DISAPPROVED) {
        // Status changed to "not approved". Disapprove work.
        if ($adminWorkHelper->disapproveWork($fields)) {
          header('Location: admin_work.php');
          exit();
        }
      } else if ($cl_status == STATUS_APPROVED) {
        // Status changed to "approved". Approve work.
        if ($adminWorkHelper->approveWork($fields)) {
          header('Location: admin_work.php');
          exit();
        }
      }
    }
  }
} // isPost

$smarty->assign('forms', array($form->getName()=>$form->toArray()));
$smarty->assign('title', $i18n->get('title.edit_work'));
$smarty->assign('content_page_name', 'admin_work_edit.tpl');
$smarty->display('index.tpl');
