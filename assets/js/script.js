document.addEventListener("DOMContentLoaded",function(){
 if(typeof paypal==="undefined") return;
 const form=document.getElementById("paypal-form");

 paypal.Buttons({
  createOrder:function(data,actions){
    const amount=form.querySelector('[name="amount"]').value;
    return actions.order.create({
      purchase_units:[{amount:{value:amount}}]
    });
  },
  onApprove:function(data,actions){
    return actions.order.capture().then(function(details){
      fetch(ajax_obj.ajaxurl,{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:new URLSearchParams({
          action:"save_payment",
          name:form.querySelector('[name="custom_name"]').value,
          email:form.querySelector('[name="custom_email"]').value,
          phone:form.querySelector('[name="custom_phone"]').value,
          amount:form.querySelector('[name="amount"]').value
        })
      });
      alert("Payment successful");
    });
  }
 }).render('#paypal-button-container');
});
