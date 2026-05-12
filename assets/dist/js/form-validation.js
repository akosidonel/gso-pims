$(function(){
    $('#admin_form').validate({// to validate administrator form input
        rules:{
          fname:{
            required:true
          },
          lname:{
            required:true
          },
          email:{
            required:true
          },
          contact:{
            required:true,number:true
          },
          emp_number:{
            required:true
          },
          role:{
            required:true
          }
        },
        messages:{
          fname:{
            required: "Please enter first name"
          },
          lname:{
            required: "Please enter last name"
          },
          email:{
            required: "Please enter email address"
          },
          contact:{
            required: "Please enter contact number", number:"Please enter numbers only"
          },
          emp_number:{
            required: "Please enter employee number"
          },
          role:{
            required: "Please select role"
          }
        },
        errorElement: 'span',
          errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.validation').append(error);
          },
          highlight: function (element, errorClass, validClass) {
            $(element).addClass('is-invalid');
          },
          unhighlight: function (element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
          }
    });

    $('#pc_form').validate({// to validate apply for property clearnce form input
      rules:{
        dept:{
          required:true
        },
        employee:{
          required:true
        },
        ctype:{
          required:true
        },
        ornumber:{
          required:true,number:true
        },
        address:{
          required:true
        },
        city:{
          required:true
        }
      },
      messages:{
        dept:{
          required: "Please select department"
        },
        employee:{
          required: "Please select employee"
        },
        ctype:{
          required: "Please select clearance type"
        },
        ornumber:{
          required: "Please enter o.r number", number:"Please enter numbers only"
        },
        address:{
          required: "Please enter address"
        },
         city:{
          required: "Please enter city"
        },
      },
      errorElement: 'span',
        errorPlacement: function (error, element) {
          error.addClass('invalid-feedback');
          element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
          $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
          $(element).removeClass('is-invalid');
        }
  });

   $('#auth_form').validate({// to validate apply for authorization clearnce form input
      rules:{
        dept:{
          required:true
        },
        parEmp:{
          required:true
        },
        ctype:{
          required:true
        },
        ornumber:{
          required:true,number:true
        },
        address:{
          required:true
        },
        city:{
          required:true
        },
        dateFrom:{
          required:true
        },
        dateTo:{
          required:true
        }
      },
      messages:{
        dept:{
          required: "Please select department"
        },
        parEmp:{
          required: "Please select employee"
        },
        ctype:{
          required: "Please select clearance type"
        },
        ornumber:{
          required: "Please enter o.r number", number:"Please enter numbers only"
        },
        address:{
          required: "Please enter address"
        },
         city:{
          required: "Please enter city"
        },
        dateFrom:{
          required: "Choose start date"
        },
        dateTo:{
          required: "Choose end date"
        }
      },
      errorElement: 'span',
        errorPlacement: function (error, element) {
          error.addClass('invalid-feedback');
          element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
          $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
          $(element).removeClass('is-invalid');
        }
  });

  $('#ref_form').validate({// to validate apply for property clearnce form input
    rules:{
      dept:{
        required:true
      },
      employee_id:{
        required:true
      },
      ctype:{
        required:true
      },
      ornumber:{
        required:true,number:true
      },
      address:{
        required:true
      },
      city:{
        required:true
      }
    },
    messages:{
      dept:{
        required: "Please select department"
      },
      employee_id:{
        required: "Please select employee"
      },
      ctype:{
        required: "Please select clearance type"
      },
      ornumber:{
        required: "Please enter o.r number", number:"Please enter numbers only"
      },
      address:{
        required: "Please enter address"
      },
       city:{
        required: "Please enter city"
      },
    },
    errorElement: 'span',
      errorPlacement: function (error, element) {
        error.addClass('invalid-feedback');
        element.closest('.form-group').append(error);
      },
      highlight: function (element, errorClass, validClass) {
        $(element).addClass('is-invalid');
      },
      unhighlight: function (element, errorClass, validClass) {
        $(element).removeClass('is-invalid');
      }
});

    $('#dept_form').validate({// to validate department form input
      rules:{
        deptname:{
          required:true
        },
        deptcode:{
          required:true
        }
      },
      messages:{
        deptname:{
          required: "Please enter department name"
        },
        deptcode:{
          required: "Please enter department code"
        }
      },
      errorElement: 'span',
        errorPlacement: function (error, element) {
          error.addClass('invalid-feedback');
          element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
          $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
          $(element).removeClass('is-invalid');
        }
  });
  $('#sef_form').validate({// to validate par special education fund form input
    rules:{
      enduser:{
        required:true
      },
      position:{
        required:true
      },
      department:{
        required:true
      },
      supplier:{
        required:true
      },
      date:{
        required:true
      },
      po:{
        required:true
      },
      pr:{
        required:true
      },
      obr:{
        required:true
      }
    },
    messages:{
      enduser:{
        required:"Please enter end user"
      },
      position:{
        required:"Please enter position"
      },
      department:{
        required:"Please select school"
      },
      supplier:{
        required:"Please enter supplier"
      },
      date:{
        required:"Please choose date"
      },
      po:{
        required:"Please enter p.o"
      },
      pr:{
        required:"Please enter p.r"
      },
      obr:{
        required:"Please enter obr"
      }
    },
    errorElement: 'span',
      errorPlacement: function (error, element) {
        error.addClass('invalid-feedback');
        element.closest('.form-group').append(error);
      },
      highlight: function (element, errorClass, validClass) {
        $(element).addClass('is-invalid');
      },
      unhighlight: function (element, errorClass, validClass) {
        $(element).removeClass('is-invalid');
      }
});
$('#emp_form').validate({// to validate employee form input
  rules:{
    firstname:{
      required:true
    },
    lastname:{
      required:true
    }
  },
  messages:{
    firstname:{
      required: "Please enter first name"
    },
    lastname:{
      required: "Please enter last name"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});

$('#inst_form').validate({// to validate school form input
  rules:{
    schoolname:{
      required:true
    },
    schoolcode:{
      required:true
    }
  },
  messages:{
    schoolname:{
      required: "Please enter school name"
    },
    schoolcode:{
      required: "Please enter school code"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});

$('#parGenForm').validate({//to validate form property input
  rules:{
    property_number:{
      required:true
    },
    date:{
      required:true
    },
    'item[]':{
      required:true
    },
    'description[]':{
      required:true
    },
    'uvalue[]':{
      required:true,
      range: [50099.99,1000000000]
    },
    dept:{
      required:true
    },
    parEmp:{
      required:true
    },
    'acode[]':{
      required:true
    },
    po:{
      required:true,number:true
    },
    obr:{
      required:true,number:true
    },
    supplier:{
      required:true
    },
    position:{
      required:true
    },
    pr:{
      required:true,number:true
    },
    property_number:{
      required:true
    },

  },
  messages:{
    property_number:{
      required: "Please enter property number"
    },
    date:{
      required: "Please select date"
    },
    'item[]':{
      required: "Please select item"
    },
    'description[]':{
      required: "Please enter description"
    },
    'uvalue[]':{
      required: "Please enter unit value"
    },
    dept:{
      required: "Please select department"
    },
    parEmp:{
      required: "Please select employee"
    },
    po:{
      required: "Please enter purchase order number",number:"Please enter numbers only"
    },
    obr:{
      required: "Please enter obr number",number:"Please enter numbers only"
    },
    supplier:{
      required: "Please select supplier"
    },
    position:{
      required: "Please enter position"
    },
    pr:{
      required: "Please enter purchase request number",number:"Please enter numbers only"
    },
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});
$('#icsGenForm').validate({//to validate form property input
  rules:{
    property_number:{
      required:true
    },
    date:{
      required:true
    },
    'item[]':{
      required:true
    },
    'description[]':{
      required:true
    },
    'uvalue[]':{
      required:true
    },
    dept:{
      required:true
    },
    parEmp:{
      required:true
    },
    'acode[]':{
      required:true
    },
    po:{
      required:true,number:true
    },
    obr:{
      required:true,number:true
    },
    supplier:{
      required:true
    },
    position:{
      required:true
    },
    pr:{
      required:true,number:true
    },
    property_number:{
      required:true
    },

  },
  messages:{
    property_number:{
      required: "Please enter property number"
    },
    date:{
      required: "Please select date"
    },
    'item[]':{
      required: "Please select item"
    },
    'description[]':{
      required: "Please enter description"
    },
    'uvalue[]':{
      required: "Please enter unit value",number:"Please enter numbers only"
    },
    dept:{
      required: "Please select department"
    },
    parEmp:{
      required: "Please select employee"
    },
    po:{
      required: "Please enter purchase order number",number:"Please enter numbers only"
    },
    obr:{
      required: "Please enter obr number",number:"Please enter numbers only"
    },
    supplier:{
      required: "Please select supplier"
    },
    position:{
      required: "Please enter position"
    },
    pr:{
      required: "Please enter purchase request number",number:"Please enter numbers only"
    },
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});
$('#ct_form').validate({//to validate form clearance type input
  rules:{
    ctname:{
      required:true
    },
    ctcode:{
      required:true
    }
  },
  messages:{
    ctname:{
      required: "Please enter clearance name"
    },
    ctcode:{
      required: "Please enter clearance code"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});

$('#supplierForm').validate({//to validate form supplier input
  rules:{
    suppliername:{
      required:true
    },
    suppliercode:{
      required:true
    }
  },
  messages:{
    suppliername:{
      required: "Please enter supplier name"
    },
    suppliercode:{
      required: "Please enter supplier code"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});
$('#supplier_update').validate({//to validate update form supplier input
  rules:{
    esuppliername:{
      required:true
    },
    esuppliercode:{
      required:true
    }
  },
  messages:{
    esuppliername:{
      required: "Please enter supplier name"
    },
    esuppliercode:{
      required: "Please enter supplier code"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});
$('#update_property_clearance').validate({//to validate update form supplier input
  rules:{
    street:{
      required:true
    },
    city:{
      required:true
    },
    zip:{
      required:true
    }
  },
  messages:{
    street:{
      required: "Please enter street address"
    },
    city:{
      required: "Please enter city"
    },
    zip:{
      required: "Please enter zip code"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});
$('#updated_authorization_clearance').validate({//to validate update form of authorization clearance
  rules:{
    address:{
      required:true
    },
    city:{
      required:true
    },
    location:{
      required:true
    },
    dateFrom:{
      required:true
    },
    dateTo:{
      required:true
    },
    remarks:{
      required:true
    }
  },
  messages:{
   address:{
      required: "Please enter address"
    },
    city:{
      required: "Please select city"
    },
    location:{
      required: "Please enter location"
    },
    dateFrom:{
      required: "Please select date from"
    },
    dateTo:{
      required: "Please select date to"
    },
    remarks:{
      required: "Please select remarks"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});
$('#update_other_clearance').validate({//to validate update form of other clearance
  rules:{
    address:{
      required:true
    },
    city:{
      required:true
    },
    remarks:{
      required:true
    }
  },
  messages:{
    address:{
      required: "Please enter street address"
    },
    city:{
      required: "Please enter city"
    },
    remarks:{
      required: "Please select remarks"
    }
  },
  errorElement: 'span',
    errorPlacement: function (error, element) {
      error.addClass('invalid-feedback');
      element.closest('.form-group').append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass('is-invalid');
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass('is-invalid');
    }
});

$('#acct_form').validate({// to validate account code form input
      rules:{
        acctname:{
          required:true
        },
        acctcode:{
          required:true
        }
      },
      messages:{
        acctname:{
          required: "Please enter account titles"
        },
        acctcode:{
          required: "Please enter account code"
        }
      },
      errorElement: 'span',
        errorPlacement: function (error, element) {
          error.addClass('invalid-feedback');
          element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
          $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
          $(element).removeClass('is-invalid');
        }
  });
  $('#acct_update').validate({ //to validate account code update form
      rules:{
        eacctname:{
          required:true
        },
        eacctcode:{
          required:true
        }
      },
      messages:{
        eacctname:{
          required: "Please enter account titles"
        },
        eacctcode:{
          required: "Please enter account code"
        }
      },
      errorElement: 'span',
        errorPlacement: function (error, element) {
          error.addClass('invalid-feedback');
          element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
          $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
          $(element).removeClass('is-invalid');
        }
  });
});

