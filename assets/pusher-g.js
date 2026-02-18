(() => {
    const restUrl = '/wp-json/form-a/all/?action=submit&_wpnonce=' +  mail_q.nonce +  '&formSlug=mail-q_pusher';

    const refresh = () => {
        window.location = window.location.href
    }

    const request = async (params, callback) => {
        r = await fetch(restUrl, { method: 'POST', body: JSON.stringify(params)}).then(r => r.json())
        if (!r.success) {
            document.getElementById('q_info').innerHTML = r.message
        } else {
            refresh()
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        let b_run = document.getElementById('q_run')
        if (b_run) {
            b_run.addEventListener('click' , () => {
                request({action: 'run'})
            })    
        }

        let b_stop = document.getElementById('q_stop')
        if (b_stop) {
            b_stop.addEventListener('click' , () => {
                request({action: 'stop'})
            })    
        }

        document.getElementById('q_refresh').addEventListener('click', () => {
            refresh()
        })

        if (mail_q.is_running) {
            setTimeout(() => {
                refresh()
            }, 10000)
        }
    })
})();