/*jshint jquery:true*/

var storage_cpf = localStorage.getItem('CpfPagHiper');

function stopRKey(evt) { 
  var evt = (evt) ? evt : ((event) ? event : null); 
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
} 

document.onkeypress = stopRKey;

window.setTimeout(function(){
    if (storage_cpf){
        document.getElementById('cpf-paghiper').value = storage_cpf;
        
        var elems = document.getElementsByClassName('btn-paghiper');

        for (var i=0;i<elems.length;i+=1){
            elems[i].style.display = 'block';
            x[i].style.color = "#0033cc";
        }
    }
}, 600);

function validCPF(){
    var x = document.getElementsByClassName('cpf-paghiper');
    var elems = document.getElementsByClassName('btn-paghiper');
    for (var i=0;i<x.length;i+=1) {
        x[i].style.color = "#CD0020";
    }
    
    var strCPF = document.getElementById('cpf-paghiper').value;

    var Soma;
    var Resto;
    Soma = 0;

    if (strCPF == "00000000000" || 
        strCPF == "11111111111" ||
        strCPF == "22222222222" ||
        strCPF == "33333333333" ||
        strCPF == "44444444444" ||
        strCPF == "55555555555" ||
        strCPF == "66666666666" ||
        strCPF == "77777777777" ||
        strCPF == "88888888888" ||
        strCPF == "99999999999"
        ){
        for (var i=0;i<elems.length;i+=1){
            elems[i].style.display = 'none';
        }

        for (var i=0;i<x.length;i+=1) {
            localStorage.removeItem('CpfPagHiper');
            x[i].style.color = "#CD0020";
        }
    } else {
        for (i=1; i<=9; i++) Soma = Soma + parseInt(strCPF.substring(i-1, i)) * (11 - i);
        Resto = (Soma * 10) % 11;

        if ((Resto == 10) || (Resto == 11))  Resto = 0;
        if (Resto != parseInt(strCPF.substring(9, 10)) ){
            for (var i=0;i<elems.length;i+=1){
                elems[i].style.display = 'none';
            }

            for (var i=0;i<x.length;i+=1) {
                localStorage.removeItem('CpfPagHiper');
                x[i].style.color = "#CD0020";
            }
        }

        Soma = 0;
        for (i = 1; i <= 10; i++) Soma = Soma + parseInt(strCPF.substring(i-1, i)) * (12 - i);
        Resto = (Soma * 10) % 11;

        if ((Resto == 10) || (Resto == 11))  Resto = 0;
        if (Resto != parseInt(strCPF.substring(10, 11) ) ){

          for (var i=0;i<x.length;i+=1) {
            localStorage.removeItem('CpfPagHiper');
            x[i].style.color = "#CD0020";
          }
        } else {
            for (var i=0;i<elems.length;i+=1){
                localStorage.setItem("CpfPagHiper", strCPF);
                elems[i].style.display = 'block';
                x[i].style.color = "#0033cc";
            } 
        }
    }

}

define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader) {
        'use strict';
        return function (messageContainer) {
            var url = window.location.href
            var arr = url.split("/");
            var result = arr[0] + "//" + arr[2];

            $.mage.redirect(result+'/paghiperpayment/index/index//'+document.getElementById('cpf-paghiper').value); //url is your url
        };
    }
);