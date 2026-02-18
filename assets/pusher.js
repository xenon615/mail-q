(() => {
    let active = false;
    let scope = {}
    const restUrl = '/wp-json/form-a/all/?action=submit&_wpnonce=' +  mail_q.nonce +  '&formSlug=mail-q_pusher';
    // const request = (params) => fetch(restUrl, { method: 'POST', body: JSON.stringify(params)}).then(r => r.json());

    const display = (count) => {
        scope.errors.innerHTML = mail_q.count.errors
        scope.total.innerHTML = mail_q.count.total
        if ((mail_q.count.total - mail_q.count.errors) == 0) {
            scope.b_start.setAttribute('disabled', true);
        }
        if ((mail_q.count.total - mail_q.count.errors) == 0) {
            scope.b_start.setAttribute('disabled', true);
        } else {
            scope.b_start.removeAttribute('disabled')
        }
        if (mail_q.count.total == 0) {
            scope.b_clear.setAttribute('disabled', true);
        }

        if (!mail_q.return_to  || mail_q.return_to == '' ) {
            scope.b_return.setAttribute('disabled', true);
        }
        if (mail_q.count.errors == 0) {
            scope.b_clear_errors.setAttribute('disabled', true);
            scope.b_export_errors.setAttribute('disabled', true);
        } else {
            scope.b_clear_errors.removeAttribute('disabled');
            scope.b_export_errors.removeAttribute('disabled', true);
        }
    }

    const switchState = () => {
        if (!active) {
            active = true;
            scope.b_start.innerHTML = 'Stop';
            scope.progress.classList.add('active');
            next();
        } else {
            scope.b_start.innerHTML = 'Start';
            active = false;
            scope.progress.classList.remove('active');
        }
    }

    const next = () => {
        if (!active) {
            return false;
        }
        request({action: 'next', count: 0 })
    }

    const request = async (params, callback) => {
        r = await fetch(restUrl, { method: 'POST', body: JSON.stringify(params)}).then(r => r.json())
        if (!r.success) {
            scope.message.innerText = r.message
        } else {
            if (r.payload && r.payload.count) {
                mail_q.count = r.payload.count
                display(mail_q.count)                
            }

            switch(params.action) {
                case 'clear':
                    window.location.href = mail_q.return_to;
                    break;
                case 'next':
                    if ((mail_q.count.total - mail_q.count.errors) == 0) {
                        scope.message.innerText = 'Emails has been sent';
                        switchState();
                    } else {
                        setTimeout(next, 500);
                    }
                    break;
                case 'export_errors':
                    let a = document.querySelector('a.invisible.download')
                    if (!a) {
                        a = document.createElement('a')
                    }
                    const bin = atob(r.payload.file)
                    const   buf = new ArrayBuffer(bin.length);
                    const view = new Uint8Array(buf);
                    for (var i = 0; i != bin.length; ++i) {
                        view[i] = bin.charCodeAt(i) & 0xFF;
                    }
                    const blob = new Blob([buf], { type: 'text/csv'})
                    a.href = window.URL.createObjectURL(blob)
                    a.setAttribute('download', r.payload.filename);
                    a.click()
                    break;
            }
            
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        scope = Object.assign(scope, {
            total: document.getElementById('total'),
            errors: document.getElementById('errors'),
            progress: document.getElementById('progress'),
            b_start: document.getElementById('start'),
            b_clear: document.getElementById('clear'),
            b_return: document.getElementById('return'),
            b_clear_errors: document.getElementById('clear_errors'),
            b_export_errors: document.getElementById('export_errors'),
            message: document.querySelector('.message')
        })
        
        display(mail_q.count)
    
        scope.b_start.addEventListener('click', (e) => {
            switchState();
        })
        scope.b_clear.addEventListener('click', (e) => {
            request({action: 'clear'});
        })

        scope.b_return.addEventListener('click', (e) => {
            window.location.href = mail_q.return_to
        });

        scope.b_clear_errors.addEventListener('click', (e) => {
            request({action: 'clear_errors'})
        });
        scope.b_export_errors.addEventListener('click', (e) => {
            request({action: 'export_errors'})
        });

    })
})()