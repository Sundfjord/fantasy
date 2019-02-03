export default {
    template: `
        <div>
            <div class="siimple-grid-row margin-bottom-10">
                <div class="siimple-btn siimple-btn--grey margin-bottom-0" @click="goBack" v-show="team"><span class="fas fa-arrow-left"></span> Back</div>
                <!--<div class="siimple-switch siimple--float-right margin-right-30">
                    <input type="checkbox" id="mySwitch" checked>
                    <label for="mySwitch"></label>
                    <div>
                </div>
                </div>-->
                <!-- <img v-show="league" height="30" class="bw margin-top-5" title="Automatically updating league results" src="/fantasy/media/live.gif"> -->
            </div>
            <div class="siimple-grid-row">
                <h1 class="margin-bottom-0">Select League for {{ team.name }} <span class="siimple--color-light">*</span></h1>
                <small class="siimple-small">* Will only summarise the top 50.</small>
            </div>
            <div class="siimple-grid-row-fullwidth">
                <div class="siimple-table siimple-table--striped siimple-table--hover clickable margin-top-20">
                    <div class="siimple-table-header siimple-table-header-compact">
                        <div class="siimple-table-row">
                            <div class="siimple-table-cell padding-right-0">League</div>
                            <div class="siimple-table-cell">Rank</div>
                        </div>
                    </div>
                    <div class="siimple-table-body">
                        <div class="siimple-table-row" v-for="league in leagues.classic" v-show="league.league_type != 's'" @click="getLeagueData(league.id)">
                            <div class="siimple-table-cell padding-right-0">{{ league.name }}</div>
                            <div class="siimple-table-cell">{{ league.entry_rank }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,
    props: ['team', 'leagues'],
    methods: {
        getLeagueData(leagueId) {
            this.$emit('loading');
            var payload = {
                teamID: this.team.id,
                leagueId: leagueId,
                info: 'live',
                page: 1
            };

            var that = this;
            $.ajax({
                url: '/fantasy/get-data.php',
                data: payload,
                timeout: 10000
            })
            .done(function(data) {
                let result = JSON.parse(data);
                that.$emit('setLeague', result.data, leagueId);
            })
            .fail(function(error) {
                if (error.statusText == "Backend fetch failed") {
                    that.getLeagueData(leagueId);
                    return;
                }
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
        },
        goBack() {
            this.$parent.goBack();
        },
    }
}
