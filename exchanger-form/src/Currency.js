import React from 'react';
class Currency extends React.Component {
    constructor( props ) {
        super( props );
        this.state = {
            exchangeRate: 27.2,
            uah: '',
            usd: ''
        }
        this.handleChangeUah = this.handleChangeUah.bind( this );
        this.handleChangeUsd = this.handleChangeUsd.bind( this );
    }

    handleChangeUah( e ) {
        this.setState( 
            {
                uah: e.target.value,
                usd: e.target.value / this.state.exchangeRate
            }
        );
    }

    handleChangeUsd( e ) {
        this.setState( 
            {
                uah: e.target.value * this.state.exchangeRate,
                usd: e.target.value
            }
        );
    }

    render() {
        return (
            <form>
                <h1 className="title">Currency exchange</h1>
                <br />
                <label>UAH
                    <input type="number" name="uah" onChange={ this.handleChangeUah } value={this.state.uah} />
                </label>
                <label>USD
                    <input type="number" name="usd" onChange={ this.handleChangeUsd } value={this.state.usd} />
                </label>
            </form>
        );
    }
}
export default Currency;