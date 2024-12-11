<?php 
/**
 * Auto accept whmcs order 
 * Developer : Rakesh Kumar
 * Email : whmcsninja@gmail.com
 * Website : whmcsninja.com
 *
 * Copyrights @ www.whmcsninja.com
 * www.whmcsninja.com
 *
 * Hook version 1.0.0
 *
 * */
use WHMCS\Database\Capsule;

// Hook para aceitar pedidos automaticamente após o checkout
add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    $serviceIDs = $vars['ServiceIDs'] ?? [];

    foreach ($serviceIDs as $serviceID) {
        $gData = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->join('tblorders', 'tblhosting.orderid', '=', 'tblorders.id')
            ->where('tblhosting.id', $serviceID)
            ->select('tblproducts.autosetup as productAutosetup', 'tblorders.id as orderid', 'tblhosting.firstpaymentamount as productAmount', 'tblhosting.id as serviceId')
            ->first();

        if (!$gData) {
            logActivity("Serviço não encontrado para o ID: $serviceID");
            continue;
        }

        $invoiceData = Capsule::table('tblorders')->where('id', $gData->orderid)->first();

        if (!$invoiceData) {
            logActivity("Pedido não encontrado para o ID: $gData->orderid");
            continue;
        }

        $invoiceID = $invoiceData->invoiceid ?? null;

        if ($invoiceID && $gData->productAutosetup === 'payment') {
            $invoiceStatus = Capsule::table('tblinvoices')->where('id', $invoiceID)->value('status');
            if ($invoiceStatus === 'Paid' && $gData->productAmount != "0.00") {
                MakeAcceptOrder($gData->orderid, $gData->serviceId);
            }
        } elseif ($gData->productAutosetup === 'order') {
            MakeAcceptOrder($gData->orderid, $gData->serviceId);
        }
    }
});

// Hook para aceitar pedidos automaticamente após pagamento da fatura
add_hook('InvoicePaid', 1, function ($vars) {
    $invoiceID = $vars['invoiceid'];

    $gData = Capsule::table('tblorders')
        ->join('tblhosting', 'tblorders.id', '=', 'tblhosting.orderid')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->where('tblorders.invoiceid', $invoiceID)
        ->select('tblproducts.autosetup as productAutosetup', 'tblorders.id as orderid', 'tblhosting.firstpaymentamount as productAmount', 'tblhosting.id as serviceId')
        ->first();

    if (!$gData) {
        logActivity("Nenhum pedido encontrado para a fatura ID: $invoiceID");
        return;
    }

    if ($gData->productAutosetup === 'order' || ($gData->productAutosetup === 'payment' && $gData->productAmount != "0.00")) {
        MakeAcceptOrder($gData->orderid, $gData->serviceId);
    }
});

/**
 * Função para aceitar pedidos automaticamente
 */
function MakeAcceptOrder($orderID = "", $serviceID = "")
{
    if (empty($orderID) || empty($serviceID)) {
        logActivity("Dados insuficientes para aceitar pedido: OrderID=$orderID, ServiceID=$serviceID");
        return;
    }

    $command = 'AcceptOrder';
    $postData = [
        'orderid' => $orderID,
        'autosetup' => '1',
        'sendemail' => '1',
    ];

    $admin = Capsule::table('tbladmins')->where('roleid', '=', 1)->first();

    if (!$admin) {
        logActivity("Nenhum administrador encontrado para executar o comando AcceptOrder.");
        return;
    }

    $adminUsername = $admin->username;

    $results = localAPI($command, $postData, $adminUsername);

    if ($results['result'] !== 'success') {
        logActivity("Erro ao aceitar pedido automaticamente: " . json_encode($results));
    } else {
        logActivity("Pedido aceito automaticamente: OrderID=$orderID, ServiceID=$serviceID");
    }
}

?>
