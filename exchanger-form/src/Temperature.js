import React from 'react';
class Temperature extends React.Component {
    constructor( props ) {
        super( props );
        this.state = {
            c: '',
            f: ''
        }
        this.handleChangeC = this.handleChangeC.bind( this );
        this.handleChangeF = this.handleChangeF.bind( this );
    }

    handleChangeC( e ) {
        this.setState( 
            {
                c: e.target.value,
                f: ( e.target.value * 9/5 ) + 32
            }
        );
    }

    handleChangeF( e ) {
        this.setState( 
            {
                c: ( e.target.value - 32 ) * 5/9,
                f: e.target.value
            }
        );
    }

    render() {
        return (
            <form>
                <h1 className="title">Temperature convertor</h1>
                <br />
                <label>C°
                    <input type="number" name="c" onChange={ this.handleChangeC } value={ this.state.c } />
                </label>
                <label>F°
                    <input type="number" name="f" onChange={ this.handleChangeF } value={ this.state.f } />
                </label>
            </form>
        );
    }
}
export default Temperature;