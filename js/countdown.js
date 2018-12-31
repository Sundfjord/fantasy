export default {
    template: `
        <div class="siimple-grid">
            <div class="siimple-grid-row-fullwidth siimple-flex">
                <div class="siimple-box siimple-box-flex day" v-show="days > 0">
                    <div class="siimple-box-title">{{ days }}</div>
                    <div class="siimple-box-subtitle">{{ days == 1 ? 'Day' : 'Days'}}</div>
                </div>
                <div class="siimple-box siimple-box-flex hour">
                    <div class="siimple-box-title">{{ hours }}</div>
                    <div class="siimple-box-subtitle">{{ hours == 1 ? 'Hour' : 'Hours'}}</div>
                </div>
                <div class="siimple-box siimple-box-flex minute">
                    <div class="siimple-box-title">{{ minutes }}</div>
                    <div class="siimple-box-subtitle">{{ minutes == 1 ? 'Minute' : 'Minutes'}}</div>
                </div>
                <div class="siimple-box siimple-box-flex second">
                    <div class="siimple-box-title">{{ seconds }}</div>
                    <div class="siimple-box-subtitle">{{ seconds == 1 ? 'Second' : 'Seconds'}}</div>
                </div>
            </div>
            <h3 class="margin-top-10 text-center">Until next Gameweek</h3>
        </div>
    `,
    props: ['timestamp'],
    data() {
        return {
            ticking: false,
            deadline: null,
            interval: "",
            days: "",
            minutes: "",
            hours: "",
            seconds: "",
        };
    },
    mounted() {
        // Update the count down every 1 second
        this.deadline = this.timestamp * 1000;
        this.timerCount(this.deadline);
        this.interval = setInterval(() => {
            this.timerCount(this.deadline);
        }, 1000);
    },
    methods: {
        timerCount(deadline) {
            if (this.ticking) {
                this.tick();
                return;
            }

            let now = new Date().getTime();
            deadline = new Date(deadline).getTime();

            // Find the distance between now and the deadline
            let timeleft = deadline - now;
            if (timeleft < 0) {
                clearInterval(this.interval);
                this.$emit('activate');
                return;
            }

            this.days = Math.floor(timeleft / (1000 * 60 * 60 * 24));
            this.hours = Math.floor((timeleft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            this.minutes = Math.floor((timeleft % (1000 * 60 * 60)) / (1000 * 60));
            this.seconds = Math.floor((timeleft % (1000 * 60)) / 1000);
            this.ticking = true;
        },
        tick() {
            // Decrements the countdown timer
            this.seconds--;
            if (this.seconds == -1) {
                this.seconds = 59;
                this.minutes--;
            }

            if (this.minutes == -1) {
                this.minutes = 59;
                this.hours--;
            }

            if (this.hours == -1) {
                this.hours = 23;
                this.days--;
            }
        }
    }
}