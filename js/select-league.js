export default {
    template: `
        <div>
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
            this.$emit('loading', true);
            var payload = {
                teamID: this.team.id,
                leagueId: parseInt(leagueId),
                info: 'live'
            };

            var that = this;
            $.get('/fantasy/get-data.php', payload)
            .done(function(data) {
                that.$emit('setLeague', JSON.parse(data), leagueId);
            })
            .fail(function(data) {
                let errorData = JSON.parse(data.responseText);
                that.error = errorData.error;
                that.$emit('loading', false);
            });
        }
    }
}
