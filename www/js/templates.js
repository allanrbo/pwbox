var Templates = {};

Templates.navigation = function() {
	
  return m('.navigation',
	  m('ul', [
	    m('li', 'A'), 
	    m('li', 'B')
	    ]
    )
	)

};