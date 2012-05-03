<?php

/* $Revision: 1.30 $ */
/* $Id$*/

include('includes/SQL_CommonFunctions.inc');

include('includes/session.inc');
$title = _('Customer Inquiry');
include('includes/header.inc');

// always figure out the SQL required from the inputs available

if(!isset($_GET['CustomerID']) AND !isset($_SESSION['CustomerID'])){
	prnMsg(_('To display the enquiry a customer must first be selected from the customer selection screen'),'info');
	echo '<br /><div class="centre"><a href="'. $rootpath . '/SelectCustomer.php">' . _('Select a Customer to Inquire On') . '</a><br /></div>';
	include('includes/footer.inc');
	exit;
} else {
	if (isset($_GET['CustomerID'])){
		$_SESSION['CustomerID'] = $_GET['CustomerID'];
	}
	$CustomerID = $_SESSION['CustomerID'];
}

if (!isset($_POST['TransAfterDate'])) {
	$sql = "SELECT confvalue
			FROM `config`
			WHERE confname ='NumberOfMonthMustBeShown'";
	$ErrMsg=_('The config value NumberOfMonthMustBeShown cannot be retrieved');
	$result = DB_query($sql,$db,$ErrMsg);
	$row = DB_fetch_array($result);
	$_POST['TransAfterDate'] = Date($_SESSION['DefaultDateFormat'],Mktime(0,0,0,Date('m')-$row['confvalue'],Date('d'),Date('Y')));
}

$SQL = "SELECT debtorsmaster.name,
		currencies.currency,
		paymentterms.terms,
		debtorsmaster.creditlimit,
		debtorsmaster.currcode,
		holdreasons.dissallowinvoices,
		holdreasons.reasondescription,
		SUM(debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount
- debtortrans.alloc) AS balance,
		SUM(CASE WHEN (paymentterms.daysbeforedue > 0) THEN
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(debtortrans.trandate)) >= paymentterms.daysbeforedue
			THEN debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount - debtortrans.alloc ELSE 0 END
		ELSE
			CASE WHEN TO_DAYS(Now()) - TO_DAYS(DATE_ADD(DATE_ADD(debtortrans.trandate, " . INTERVAL('1', 'MONTH') . "), " . INTERVAL('(paymentterms.dayinfollowingmonth - DAYOFMONTH(debtortrans.trandate))', 'DAY') . ")) >= 0 THEN debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount - debtortrans.alloc ELSE 0 END
		END) AS due,
		SUM(CASE WHEN (paymentterms.daysbeforedue > 0) THEN
			CASE WHEN TO_DAYS(Now()) - TO_DAYS(debtortrans.trandate) > paymentterms.daysbeforedue
			AND TO_DAYS(Now()) - TO_DAYS(debtortrans.trandate) >= (paymentterms.daysbeforedue + " .
		$_SESSION['PastDueDays1'] . ")
			THEN debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount - debtortrans.alloc ELSE 0 END
		ELSE
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(DATE_ADD(DATE_ADD(debtortrans.trandate, ". INTERVAL('1', 'MONTH') . "), " . INTERVAL('(paymentterms.dayinfollowingmonth - DAYOFMONTH(debtortrans.trandate))','DAY') . ")) >= " . $_SESSION['PastDueDays1'] . ")
			THEN debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount
			- debtortrans.alloc ELSE 0 END
		END) AS overdue1,
		SUM(CASE WHEN (paymentterms.daysbeforedue > 0) THEN
			CASE WHEN TO_DAYS(Now()) - TO_DAYS(debtortrans.trandate) > paymentterms.daysbeforedue
			AND TO_DAYS(Now()) - TO_DAYS(debtortrans.trandate) >= (paymentterms.daysbeforedue + " . $_SESSION['PastDueDays2'] . ") THEN debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount - debtortrans.alloc ELSE 0 END
		ELSE
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(DATE_ADD(DATE_ADD(debtortrans.trandate, " . INTERVAL('1','MONTH') . "), " . INTERVAL('(paymentterms.dayinfollowingmonth - DAYOFMONTH(debtortrans.trandate))','DAY') . ")) >= " . $_SESSION['PastDueDays2'] . ") THEN debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount - debtortrans.alloc ELSE 0 END
		END) AS overdue2
		FROM debtorsmaster,
     			paymentterms,
     			holdreasons,
     			currencies,
     			debtortrans
		WHERE  debtorsmaster.paymentterms = paymentterms.termsindicator
     		AND debtorsmaster.currcode = currencies.currabrev
     		AND debtorsmaster.holdreason = holdreasons.reasoncode
     		AND debtorsmaster.debtorno = '" . $CustomerID . "'
     		AND debtorsmaster.debtorno = debtortrans.debtorno
 		GROUP BY debtorsmaster.name,
			currencies.currency,
			paymentterms.terms,
			paymentterms.daysbeforedue,
			paymentterms.dayinfollowingmonth,
			debtorsmaster.creditlimit,
			holdreasons.dissallowinvoices,
			holdreasons.reasondescription";

$ErrMsg = _('The customer details could not be retrieved by the SQL because');
$CustomerResult = DB_query($SQL,$db,$ErrMsg);

if (DB_num_rows($CustomerResult)==0){

	/*Because there is no balance - so just retrieve the header information about the customer - the choice is do one query to get the balance and transactions for those customers who have a balance and two queries for those who don't have a balance OR always do two queries - I opted for the former */

	$NIL_BALANCE = True;

	$SQL = "SELECT debtorsmaster.name, currencies.currency, paymentterms.terms,
	debtorsmaster.creditlimit, holdreasons.dissallowinvoices, holdreasons.reasondescription
	FROM debtorsmaster,
	     paymentterms,
	     holdreasons,
	     currencies
	WHERE
	     debtorsmaster.paymentterms = paymentterms.termsindicator
	     AND debtorsmaster.currcode = currencies.currabrev
	     AND debtorsmaster.holdreason = holdreasons.reasoncode
	     AND debtorsmaster.debtorno = '" . $CustomerID . "'";

	$ErrMsg =_('The customer details could not be retrieved by the SQL because');
	$CustomerResult = DB_query($SQL,$db,$ErrMsg);

} else {
	$NIL_BALANCE = False;
}

$CustomerRecord = DB_fetch_array($CustomerResult);

if ($NIL_BALANCE==True){
	$CustomerRecord['balance']=0;
	$CustomerRecord['due']=0;
	$CustomerRecord['overdue1']=0;
	$CustomerRecord['overdue2']=0;
}

	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/customer.png" title="' . _('Customer') . '" alt="" />' .
	' ' . _('Customer') . ' : ' . $CustomerRecord['name'] . ' - (' . _('All amounts stated in') .
	' ' . $CustomerRecord['currency'] . ')<br /><br />' . _('Terms') . ' : ' . $CustomerRecord['terms'] . '<br />' . _('Credit Limit') .
	': ' . locale_money_format($CustomerRecord['creditlimit'],$CustomerRecord['currcode']) . ' ' . _('Credit Status') . ': ' . $CustomerRecord['reasondescription'] . '</p>';

if ($CustomerRecord['dissallowinvoices']!=0){
	echo '<br /><font color="red" size="4"><b>' . _('ACCOUNT ON HOLD') . '</font></b><br />';
}

echo '<table class="selection" width=70%>
	<tr>
		<th width="20%">' . _('Total Balance') . '</th>
		<th width="20%">' . _('Current') . '</th>
		<th width="20%">' . _('Now Due') . '</th>
		<th width="20%">' . $_SESSION['PastDueDays1'] . '-' . $_SESSION['PastDueDays2'] . ' ' . _('Days Overdue') . '</th>
		<th width="20%">' . _('Over') . ' ' . $_SESSION['PastDueDays2'] . ' ' . _('Days Overdue') . '</th></tr>';

echo '<tr><td class="number">' . locale_money_format($CustomerRecord['balance'],$CustomerRecord['currcode']) . '</td>
	<td class="number">' . locale_money_format(($CustomerRecord['balance'] - $CustomerRecord['due']),$CustomerRecord['currcode']) . '</td>
	<td class="number">' . locale_money_format(($CustomerRecord['due']-$CustomerRecord['overdue1']),$CustomerRecord['currcode']) . '</td>
	<td class="number">' . locale_money_format(($CustomerRecord['overdue1']-$CustomerRecord['overdue2']) ,$CustomerRecord['currcode']) . '</td>
	<td class="number">' . locale_money_format($CustomerRecord['overdue2'],$CustomerRecord['currcode']) . '</td>
	</tr>
	</table>';

echo '<br /><form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<table class="selection">
		<tr>
			<td>' . _('Show all transactions after') . ': </td>
			<td><input tabindex="1" type="text" class="date" alt="'.$_SESSION['DefaultDateFormat'].'" id="datepicker" name="TransAfterDate" value="' . $_POST['TransAfterDate'] . '" maxlength="10" size="12" /></td>
			<td><button tabindex="2" type="submit" name="Refresh Inquiry">' . _('Refresh Inquiry') . '</button></td>
		</tr>
	</table></form><br />';

$DateAfterCriteria = FormatDateForSQL($_POST['TransAfterDate']);


$SQL = "SELECT systypes.typename,
		debtortrans.id,
		debtortrans.type,
		debtortrans.transno,
		debtortrans.branchcode,
		debtortrans.trandate,
		debtortrans.reference,
		debtortrans.invtext,
		debtortrans.order_,
		debtortrans.rate,
		(debtortrans.ovamount + debtortrans.ovgst + debtortrans.ovfreight + debtortrans.ovdiscount) AS totalamount,
		debtortrans.alloc AS allocated,
		debtortype.typename
	FROM debtortrans,
		systypes,
		debtorsmaster,
		debtortype
	WHERE debtortrans.type = systypes.typeid
	AND debtorsmaster.debtorno=debtortrans.debtorno
	AND debtorsmaster.typeid=debtortype.typeid
	AND debtortrans.debtorno = '" . $CustomerID . "'
	AND debtortrans.trandate >= '$DateAfterCriteria'
	ORDER BY debtortrans.id";

$ErrMsg = _('No transactions were returned by the SQL because');
$TransResult = DB_query($SQL,$db,$ErrMsg);

if (DB_num_rows($TransResult)==0){
	echo '<div class="centre">' . _('There are no transactions to display since') . ' ' . $_POST['TransAfterDate'] . '</div>';
	include('includes/footer.inc');
	exit;
}
/*show a table of the invoices returned by the SQL */

echo '<table class="selection" cellpadding="2">';

$tableheader = '<tr>
					<th>' . _('Type') . '</th>
					<th>' . _('Number') . '</th>
					<th>' . _('Date') . '</th>
					<th>' . _('Branch') . '</th>
					<th>' . _('Reference') . '</th>
					<th>' . _('Comments') . '</th>
					<th>' . _('Order') . '</th>
					<th>' . _('Total') . '</th>
					<th>' . _('Allocated') . '</th>
					<th>' . _('Balance') . '</th>
					<th>' . _('More Info') . '</th>
					<th>' . _('More Info') . '</th>
					<th>' . _('More Info') . '</th>
					<th>' . _('More Info') . '</th>
					<th>' . _('More Info') . '</th>
				</tr>';

echo $tableheader;

$j = 1;
$k=0; //row colour counter
while ($myrow=DB_fetch_array($TransResult)) {

	if ($k==1){
		echo '<tr class="EvenTableRows">';
		$k=0;
	} else {
		echo '<tr class="OddTableRows">';
		$k=1;
	}

	$FormatedTranDate = ConvertSQLDate($myrow['trandate']);

	if ($_SESSION['InvoicePortraitFormat']==1 and $myrow['typename']!='Insurance'){ //Invoice/credits in portrait
		$PrintCustomerTransactionScript = 'PrintCustTransPortrait.php';
	} else if ($myrow['typename']!='Insurance'){ //produce pdfs in landscape
		$PrintCustomerTransactionScript = 'PrintCustTrans.php';
	} else { //produce pdfs in landscape
		$PrintCustomerTransactionScript = 'PrintInsuranceInvoice.php';
	}

	$BaseFormatString = '<td>%s</td>
										<td>%s</td>
										<td>%s</td>
										<td>%s</td>
										<td>%s</td>
										<td width="200">%s</td>
										<td>%s</td>
										<td class="number">%s</td>
										<td class="number">%s</td>
										<td class="number">%s</td>';

	$CreditInvoiceFormatString = '<td><a href="%s/Credit_Invoice.php?InvoiceNumber=%s">' . _('Credit ') .'<img src="%s/credit.gif" title="' . _('Click to credit the invoice') . '" /></a></td>';

	$PreviewInvoiceFormatString = '<td><a href="%s/PrintCustTrans.php?FromTransNo=%s&InvOrCredit=Invoice">' . _('HTML ') . '<img src="%s/preview.gif" title="' . _('Click to preview the invoice') . '" /></a></td>
	<td><a href="%s/%s?FromTransNo=%s&InvOrCredit=Invoice&PrintPDF=True">' . _('PDF ') . '<img src="%s/pdf.png" title="' . _('Click for PDF') . '" /></a></td>
		<td><a href="%s/EmailCustTrans.php?FromTransNo=%s&InvOrCredit=Invoice">' . _('Email ') . '<img src="%s/email.gif" title="' . _('Click to email the invoice') . '" /></a></td>';

	$PreviewCreditFormatString = '<td><a href="%s/PrintCustTrans.php?FromTransNo=%s&InvOrCredit=Credit">' . _('HTML ') . ' <img src="%s/preview.gif" title="' . _('Click to preview the credit note') . '" /></a></td>
	<td><a href="%s/%s?FromTransNo=%s&InvOrCredit=Credit&PrintPDF=True">' . _('PDF ') . '<img src="%s/pdf.png" title="' . _('Click for PDF') . '" /></a></td>
	<td><a href="%s/EmailCustTrans.php?FromTransNo=%s&InvOrCredit=Credit">' . _('Email') . ' <img src="%s/email.gif" title="' . _('Click to email the credit note') . '" /></a></td>';

	/* assumed allowed page security token 3 allows the user to create credits for invoices */
	if (in_array(3,$_SESSION['AllowedPageSecurityTokens']) and $myrow['type']==10){
		/*Show a link to allow an invoice to be credited */

		/* assumed allowed page security token 8 allows the user to see GL transaction information */
		if ($_SESSION['CompanyRecord']['gllink_debtors']== 1 AND in_array(8,$_SESSION['AllowedPageSecurityTokens'])){

			/* format string with GL inquiry options and for invoice to be credited */

			printf($BaseFormatString . $CreditInvoiceFormatString . $PreviewInvoiceFormatString .
				'<td><a href="%s/GLTransInquiry.php?TypeID=%s&TransNo=%s">' . _('View GL Entries') . ' <img src="' .$rootpath. '/css/'.$theme.'/images/gl.png" title="' . _('View the GL Entries') . '" /></a></td>
				</tr>',
				//$BaseFormatString parameters
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				//$CreditInvoiceFormatString parameters
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				//$PreviewInvoiceFormatString parameters
				$rootpath,
                $myrow['transno'],
                $rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$PrintCustomerTransactionScript,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				//Parameter for string for GL Trans Inquiries
				$rootpath,
				$myrow['type'],
				$myrow['transno']);
		} else { //user does not have privileges to see GL inquiry stuff

			printf($BaseFormatString . $CreditInvoiceFormatString . $PreviewInvoiceFormatString . 	'</tr>',
				//BaseFormatString parameters
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				//CreditInvoiceFormatString parameters
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
			//$PreviewInvoiceFormatString parameters
				$rootpath,
                $myrow['transno'],
                $rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$PrintCustomerTransactionScript,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images');
		}

	} elseif($myrow['type']==10) { /*its an invoice but not high enough priveliges to credit it */

		printf($BaseFormatString .
			$PreviewInvoiceFormatString .
			'</tr>',
			//$BaseFormatString parameters
			$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
			//$PreviewInvoiceFormatString parameters
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$PrintCustomerTransactionScript,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images');

	} elseif ($myrow['type']==11) { /*its a credit note */
		if ($_SESSION['CompanyRecord']['gllink_debtors']== 1 AND in_array(8,$_SESSION['AllowedPageSecurityTokens'])){
			printf($BaseFormatString .
				$PreviewCreditFormatString .
				'<td><a href="%s/CustomerAllocations.php?AllocTrans=%s">' . _('Allocation') . '<img src="' .$rootpath.'/css/'.$theme.'/images/allocation.png" title="' . _('Click to allocate funds') . '" /></a></td>
				<td><a href="%s/GLTransInquiry.php?TypeID=%s&TransNo=%s">' . _('View GL Entries') . ' <a><img src="' .$rootpath.'/css/'.$theme.'/images/gl.png" title="' . _('View the GL Entries') . '" /></a></td></tr>',
				//$BaseFormatString parameters
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				//$PreviewCreditFormatString parameters
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$PrintCustomerTransactionScript,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				// hand coded format string for Allocations and GLTrans Inquiry parameters
				$rootpath,
				$myrow['id'],
				$rootpath,
				$myrow['type'],
				$myrow['transno']);
		} else {
			printf($BaseFormatString .
				$PreviewCreditFormatString .
				'<td><a href="%s/CustomerAllocations.php?AllocTrans=%s">' . _('Allocation') . '<img src="%s/allocation.png" title="' . _('Click to allocate funds') . '" /></a></td>
				</tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				//$PreviewCreditFormatString parameters
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$PrintCustomerTransactionScript,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				$rootpath,
				$myrow['transno'],
				$rootpath.'/css/'.$theme.'/images',
				//Parameters for hand coded string to show allocations
				$rootpath,
				$myrow['id'],
				$rootpath.'/css/'.$theme.'/images');
		}
	} elseif ($myrow['type']==12 AND $myrow['totalamount']<0) { /*its a receipt  which could have an allocation*/

		//If security token 8 in the allowed page security tokens then assumed ok for GL trans inquiries
		if ($_SESSION['CompanyRecord']['gllink_debtors']== 1 AND in_array(8,$_SESSION['AllowedPageSecurityTokens'])){
			printf($BaseFormatString .
				'<td><a href="%s/CustomerAllocations.php?AllocTrans=%s">' . _('Allocation') . '<img src="' .$rootpath.'/css/'.$theme.'/images/allocation.png" title="' . _('Click to allocate funds') . '" /></a></td>
				<td><a href="%s/GLTransInquiry.php?TypeID=%s&TransNo=%s">' . _('View GL Entries') . ' <img src="' .$rootpath.'/css/'.$theme.'/images/gl.png" title="' . _('View the GL Entries') . '" /></a></td>
				</tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				$rootpath,
				$myrow['id'],
				$rootpath,
				$myrow['type'],
				$myrow['transno']);
		} else { //no permission for GLTrans Inquiries
			printf($BaseFormatString .
				'<td><a href="%s/CustomerAllocations.php?AllocTrans=%s">' . _('Allocation') . '<img src="' .$rootpath.'/css/'.$theme.'/images/allocation.png" title="' . _('Click to allocate funds') . '" /></a></td>
				</tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				$rootpath,
				$myrow['id']);
		}
	} elseif ($myrow['type']==12 AND $myrow['totalamount']>0) { /*its a negative receipt */

		//If security token 8 in the allowed page security tokens then assumed ok for GL trans inquiries
		if ($_SESSION['CompanyRecord']['gllink_debtors']== 1 AND in_array(8,$_SESSION['AllowedPageSecurityTokens'])){
			printf($BaseFormatString .
				'<td><a href="%s/GLTransInquiry.php?TypeID=%s&TransNo=%s">' . _('View GL Entries') . ' <a></td></tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				$rootpath,
				$myrow['type'],
				$myrow['transno']);
		} else { //no permission for GLTrans Inquiries
			printf($BaseFormatString . '<td></tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']));
		}
	} else {
		//If security token 8 in the allowed page security tokens then assumed ok for GL trans inquiries
		if ($_SESSION['CompanyRecord']['gllink_debtors']== 1 AND in_array(8,$_SESSION['AllowedPageSecurityTokens'])){
			printf($BaseFormatString .
				'<td><a href="%s/GLTransInquiry.php?TypeID=%s&TransNo=%s">' . _('View GL Entries') . ' <a></td></tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']),
				$rootpath,
				$myrow['type'],
				$myrow['transno']);
		} else {
			printf($BaseFormatString . '</tr>',
				$myrow['typename'],
				$myrow['transno'],
				ConvertSQLDate($myrow['trandate']),
				$myrow['branchcode'],
				$myrow['reference'],
				$myrow['invtext'],
				$myrow['order_'],
				locale_money_format($myrow['totalamount'],$CustomerRecord['currcode']),
				locale_money_format($myrow['allocated'],$CustomerRecord['currcode']),
				locale_money_format($myrow['totalamount']-$myrow['allocated'],$CustomerRecord['currcode']));
		}
	}

}
//end of while loop

echo '</table>';
include('includes/footer.inc');
?>