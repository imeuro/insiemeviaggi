addEventListener("DOMContentLoaded", (event) => {

	
    let additionalFormContainer = document.querySelector('.woocommerce-custom-LTC-fields');
	if (additionalFormContainer) {
		console.debug({additionalFormContainer});

		// rimuovo css non desiderato
		document.querySelector('link[href$="/happyforms/bundles/css/frontend.css"]').disabled = true;

		// spezzare pagina..
		let FormHeadings = document.querySelector('.col-2');
		let rawFormHeadings = FormHeadings.innerHTML;

	    // lo wrappo in un div pero'
	    var w_muvit = document.createElement('div');
		// insert wrapper before FormHeadings in the DOM tree
		additionalFormContainer.parentNode.insertBefore(w_muvit, additionalFormContainer);
		// move FormHeadings into wrapper
		w_muvit.appendChild(additionalFormContainer);
		// inserisce il form prima dell'ultima slide
	    FormHeadings.before(w_muvit);


	    _formContainer = document.getElementById('customer_details');
		_formContainer.parentNode.classList.add('checkoutSlider');
		_formContainer.classList = 'checkoutSlides';

		let formNav = document.createElement('nav');
		formNav.classList = 'form-navigation';
		_formContainer.parentNode.insertBefore(formNav, _formContainer);

		Array.from(_formContainer.children).forEach((el, i) => {
			console.debug({el});
			console.debug({i});
			let nslide = i+1;
			el.id="slide-"+nslide;
			let slidebutton = document.createElement('a');
			slidebutton.href="#slide-"+nslide;
			slidebutton.id="to_slide"+nslide;
			if (nslide==1) {
				slidebutton.classList="active";
			}

			slidebutton.addEventListener('click',(event)=>{
				event.preventDefault();
				prevYpos = window.pageYOffset;
				location.href = '#slide-'+nslide;
				window.scrollTo({
					top: prevYpos,
					left: 0,
					behavior: 'auto'
				});

				Array.from(document.querySelectorAll(".form-navigation a")).forEach((el)=>{
					el.classList='';
				})
				document.getElementById("to_slide"+nslide).classList.add('active');
			});

			slidebutton.innerHTML = nslide;
			formNav.appendChild(slidebutton);
		});

		// let formNavClone = formNav.cloneNode(true);
		// formNavClone.classList.add('bottom-nav');
		// _formContainer.append(formNavClone);

		
	}


});