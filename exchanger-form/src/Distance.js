import React from 'react';
class Distance extends React.Component {
    constructor( props ) {
        super( props );
        this.state = {
            m: '',
            km: ''
        }
        this.handleChangeM = this.handleChangeM.bind( this );
        this.handleChangeKm = this.handleChangeKm.bind( this );
    }

    handleChangeM( e ) {
        this.setState( 
            {
                m: e.target.value,
                km: e.target.value / 1000
            }
        );
    }

    handleChangeKm( e ) {
        this.setState( 
            {
                m:  e.target.value * 1000,
                km: e.target.value
            }
        );
    }

    render() {
        return (
            <form>
                <h1 className="title">Distance convertor</h1>
                <br />
                <label>M
                    <input type="number" name="m" onChange={ this.handleChangeM } value={ this.state.m } />
                </label>
                <label>KM
                    <input type="number" name="km" onChange={ this.handleChangeKm } value={ this.state.km } />
                </label>
            </form>
        );
    }
}
export default Distance;