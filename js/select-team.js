  export default {
  template: `
    <div class="siimple-grid-row">
        <h1 class="margin-top-0">Select Team</h1>
        <div class="siimple-input-group">
            <div class="siimple-close siimple-input-close" v-if="error"></div>
            <input type="text" v-model="teamID" @input="validate()" placeholder="Your Team ID.." class="siimple-input siimple-input--fluid siimple-input-big" @keyup.enter="findTeam()">
            <span class="siimple-input-group-addon siimple-btn siimple-btn--success" :class="{'siimple-btn--disabled': this.error.length}" :disabled="this.error.length" @click="findTeam()">Check</span>
            <div class="siimple-field-helper siimple-field-helper--error" v-show="error.length">{{ error }}</div>
        </div>
        <div class="siimple-help text-center">
            <a class="siimple-link" @click="showHelp = !showHelp">How do I find my team ID?</a><br>
            <span v-show="showHelp">
                <img width="275" style="margin-top: 10px; border-right: 1px solid #f2f2f2" src="/fantasy/media/teamID.png"><br>
                <small class="siimple-small">When on the My Points page, you can copy it from the URL.</small>
            </span>
        </div>
    </div>
    `,
    data() {
        return {
            teamID: '',
            error: '',
            showHelp: false,
        }
    },
    methods: {
        validate() {
            if (this.teamID == '') {
                this.error = '';
                return true;
            }
            if (!this.teamID.match(/^\d+$/)) {
                this.error = 'Only numbers are allowed in this field';
                return false;
            }

            this.error = '';
            return true;
        },
        findTeam() {
            if (this.error.length || this.teamID == '') {
                return;
            }

            this.$emit('loading');
            var payload = {
                teamId: parseInt(this.teamID),
                info: 'team'
            };

            var that = this;
            $.ajax({
                url: '/fantasy/get-data.php',
                data: payload,
                timeout: 20000
            })
            .done(function(data) {
                let result = JSON.parse(data);
                that.$emit('setTeam', result.data);
            })
            .fail(function(error) {
                if (error.statusText == "timeout") {
                    that.$emit('showError', 'Unable to fetch Fantasy data. Please try again.');
                    return;
                }

                let errorData = JSON.parse(error.responseText);
                that.$emit('showError', errorData.error);
            })
            .always(function(data) {
                if (typeof data == "object") {
                    return;
                }
                let result = JSON.parse(data);
                console.log(result.duration);
            });
        }
    }
  }
