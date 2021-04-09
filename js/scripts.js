function show_form(){
    var form_add = $('div.add_com_block');
    
    if(form_add.css('display') == 'none'){
        form_add.slideDown(400);
        $('.add_com_but').hide();      
    }
    else{               
        form_add.slideUp(400);
        $('.add_com_but').show();               
    }   
}