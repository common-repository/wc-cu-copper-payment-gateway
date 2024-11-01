/**
 * Bond Ethereum address to account
 * */
async function copperPaymentGatewayRequestSignature(payData) {
    let {message, displayMessages, security, ajaxurl} = payData;
    if (window.ethereum) {
        window.web3 = new Web3(ethereum);
        try {
            ethereum.enable();
        } catch (error) {
            console.log(error)
        }

    } else if (window.web3) {
        window.web3 = new Web3(web3.currentProvider);
    } else {
        let logsElement = document.getElementById('copper-payment-gateway__logs');
        logsElement.innerHTML = displayMessages['install-metamask'];
        logsElement.classList.add('has_error');
        return;
    }

    let accounts = await ethereum.request({method: 'eth_requestAccounts'});
    let from;
    let sign;
    try {
        from = accounts[0];
        sign = await ethereum.request({
            method: 'personal_sign',
            params: [message, from],
        });
    } catch (err) {
        console.error(err);
        return;
    }

    const data = {
        'action': 'copper_payment_gateway_add_eth_address_to_account',
        'sign': sign,
        'sender': from,
        'security': security
    };

    jQuery.post(ajaxurl, data, function (response) {
        copperPaymentGatewayHandleRequestSignatureResponse(JSON.parse(response), payData);
    });
}

function copperPaymentGatewayHandleRequestSignatureResponse(data, payData) {
    let logsElement = document.getElementById('copper-payment-gateway__logs');
    let connectedAddresses = document.getElementById('cu-connected-addresses');
    let connectedAddressesList = document.getElementsByClassName('cu-connected-addresses__list')[0];

    if (data['action'] !== 'copper_payment_gateway_add_eth_address_to_account') {
        return;
    }

    if (!data['done']) {
        logsElement.classList.add('has_error');
        logsElement.innerHTML = data['error'];
        return;
    }

    if (logsElement.classList.contains('has_error')) {
        logsElement.classList.remove('has_error');
    }

    logsElement.innerHTML = data['success'];

    let listItem = document.createElement('li');
    listItem.classList.add('cu-connected-addresses__list-item');
    listItem.dataset.cuAddress = data['account'];
    listItem.id = 'cu-address-' + data['account'];

    let span = document.createElement('span')
    span.classList.add('cu-connected-addresses__span');
    span.innerHTML = data['account'];
    listItem.append(span);

    let deleteButton = document.createElement('button')
    deleteButton.classList.add('cu-connected-addresses__delete-button');
    deleteButton.onclick = function () {
        copperPaymentGatewayRemoveAddress(data['account'], payData);
    }
    deleteButton.innerHTML = 'X';
    listItem.append(deleteButton);

    if (copperPaymentGatewayAddresses.length > 0) {
        connectedAddressesList.append(listItem);
    } else {
        const node = document.getElementsByClassName('cu-connected-addresses__empty')[0];
        node.parentNode.removeChild(node);

        let list = document.createElement('ul')
        list.classList.add('cu-connected-addresses__list');

        list.append(listItem);
        connectedAddresses.append(list);
    }

    copperPaymentGatewayAddresses.push(data['account']);
    copperPaymentGatewaySetButtonText();
}

/**
 * Remove Ethereum account address
 * */
async function copperPaymentGatewayRemoveAddress(address, {displayMessages, security, ajaxurl}) {
    const data = {
        'action': 'copper_payment_gateway_remove_eth_address_from_account',
        'address': address,
        'security': security
    };

    jQuery.post(ajaxurl, data, function (response) {
        copperPaymentGatewayHandleRemoveAddressResponse(JSON.parse(response), displayMessages);
    });
}

function copperPaymentGatewayHandleRemoveAddressResponse(data, displayMessages) {
    let connectedAddressesElement = document.getElementById('cu-connected-addresses');
    let logsElement = document.getElementById('copper-payment-gateway__logs');
    let connectedAddressesList = document.getElementsByClassName('cu-connected-addresses__list')[0];

    if (data['action'] !== 'copper_payment_gateway_remove_eth_address_from_account') {
        return;
    }

    if (!data['done']) {
        logsElement.classList.add('has_error');
        logsElement.innerHTML = data['error'];
        return;
    }

    if (logsElement.classList.contains('has_error')) {
        logsElement.classList.remove('has_error');
    }

    logsElement.innerHTML = data['success'];

    let nodeId = 'cu-address-' + data['account'];
    const node = document.getElementById(nodeId);
    node.parentNode.removeChild(node);

    const index = copperPaymentGatewayAddresses.indexOf(data['account']);
    if (index > -1) {
        copperPaymentGatewayAddresses.splice(index, 1);
    }

    if (copperPaymentGatewayAddresses.length < 1) {
        connectedAddressesElement.removeChild(connectedAddressesList);

        let div = document.createElement('div')
        div.classList.add('cu-connected-addresses__empty');
        div.innerHTML = displayMessages['no-addresses'];
        connectedAddressesElement.append(div);
    }
    if (copperPaymentGatewayHasButton) {
        copperPaymentGatewaySetButtonText();
    }
}

/**
 * Payment
 * */
function copperPaymentGatewayRequestPayment({
                                 amount,
                                 displayMessages,
                                 security,
                                 orderId,
                                 abiArray,
                                 contractAddress,
                                 targetAddress,
                                 ajaxurl
                             }) {
    if (window.ethereum) {
        window.web3 = new Web3(ethereum);
        try {
            ethereum.enable();
        } catch (error) {
            console.log(error)
        }

    } else if (window.web3) {
        window.web3 = new Web3(web3.currentProvider);
    } else {
        let logsElement = document.getElementById('copper-payment-gateway__logs');
        logsElement.innerHTML = displayMessages['install-metamask'];
        logsElement.classList.add('has_error');
        return;
    }

    var erc_contract = web3.eth.contract(abiArray);
    var erc_contract_instance = erc_contract.at(contractAddress);
    erc_contract_instance.transfer(targetAddress, amount * 1E18, function (error, result) {
        if (error === null && result !== null) {
            const data = {
                'action': 'copper_payment_gateway_check_transaction',
                'order_id': orderId,
                'tx': result,
                'security': security
            };
            console.log(result);
            jQuery.post(ajaxurl, data, function (response) {
                console.log('Response');
                console.log(response);
            });
        }
    });
}

/**
 * Controller
 * */
async function copperPaymentGatewaySetButtonText() {
    let message = copperPaymentGatewayData['displayMessages'];
    let button = document.getElementById('copper-payment-gateway__pay-button');

    if (!window.ethereum || !window.web3) {
        button.innerHTML = message['pay-order-btn'];
        button.disabled = true;
        let logs = document.getElementById('copper-payment-gateway__logs');
        logs.innerHTML = message['install-metamask'];
        return;
    }

    let accounts = await ethereum.request({method: 'eth_requestAccounts'});
    let currentAccount = accounts[0];

    if (!currentAccount) {
        button.innerHTML = message['connect-metamask-btn'];
        return;
    }

    if (!copperPaymentGatewayAddresses.includes(currentAccount)) {
        button.innerHTML = message['bound-account-btn'];
        return;
    }

    button.innerHTML = message['pay-order-btn'];
}

async function copperPaymentGatewayShowCurrentAccount() {
    let accounts = await ethereum.request({method: 'eth_requestAccounts'});
    let currentAccount = accounts[0];
    let element = document.getElementById('copper-payment-gateway__current-provider-account');
    if (!currentAccount) {
        element.innerHTML = '...';
        return;
    }

    element.innerHTML = currentAccount;
}

async function copperPaymentGatewayPay(payData) {
    let {displayMessages} = payData;
    if (window.ethereum) {
        window.web3 = new Web3(ethereum);
        try {
            ethereum.enable();
        } catch (error) {
            console.log(error)
        }

    } else if (window.web3) {
        window.web3 = new Web3(web3.currentProvider);
    } else {
        let logsElement = document.getElementById('copper-payment-gateway__logs');
        logsElement.innerHTML = displayMessages['install-metamask'];
        logsElement.classList.add('has_error');
        return;
    }

    let accounts = await ethereum.request({method: 'eth_requestAccounts'});
    let current_account = accounts[0];
    if (!copperPaymentGatewayAddresses.includes(current_account)) {
        await copperPaymentGatewayRequestSignature(payData);
    } else {
        copperPaymentGatewayRequestPayment(payData);
    }
}
